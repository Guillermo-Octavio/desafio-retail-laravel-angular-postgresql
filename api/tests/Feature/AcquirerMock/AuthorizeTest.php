<?php

declare(strict_types=1);

use App\AcquirerMock\AcquirerMockServiceProvider;
use App\AcquirerMock\Http\AuthorizeRequest;
use Illuminate\Support\Facades\Validator;
use Tests\Support\AcquirerMockRequests;
use Tests\Support\CapturedLogs;

beforeEach(function () {
    config(['acquirer_mock.enabled' => true]);
    $this->app->register(AcquirerMockServiceProvider::class, force: true);
});

it('approves a valid card with an authorization code', function (string $cardNumber) {
    $response = $this->postJson('/acquirer/authorize', AcquirerMockRequests::authorization($cardNumber));

    $response->assertOk()->assertJsonPath('status', 'APPROVED');
    expect($response->json('authorization_code'))->toMatch('/^[A-Z0-9]{6}$/');
})->with(['4111111111111111', '5555555555554444']);

it('declines the insufficient funds test card', function () {
    $this->postJson('/acquirer/authorize', AcquirerMockRequests::authorization('4000000000000002'))
        ->assertOk()
        ->assertExactJson(['status' => 'DECLINED', 'reason' => 'INSUFFICIENT_FUNDS']);
});

it('is always temporarily unavailable for the failing test card', function () {
    foreach (range(1, 3) as $attempt) {
        $this->postJson('/acquirer/authorize', AcquirerMockRequests::authorization('4000000000000119'))
            ->assertStatus(503)
            ->assertExactJson(['error' => 'TEMPORARY_UNAVAILABLE']);
    }
});

it('fails once and then approves the recovering test card, per transaction', function () {
    $first = AcquirerMockRequests::authorization('4000000000000259', '0199f0c2-6a1e-7c3a-9b2f-000000000001');
    $second = AcquirerMockRequests::authorization('4000000000000259', '0199f0c2-6a1e-7c3a-9b2f-000000000002');

    $this->postJson('/acquirer/authorize', $first)->assertStatus(503);
    $this->postJson('/acquirer/authorize', $first)->assertOk()->assertJsonPath('status', 'APPROVED');
    $this->postJson('/acquirer/authorize', $second)->assertStatus(503);
});

it('rejects a malformed authorization request', function (array $change) {
    $this->postJson('/acquirer/authorize', array_replace_recursive(AcquirerMockRequests::authorization(), $change))
        ->assertStatus(422);
})->with([
    'missing transaction id' => [['transaction_id' => null]],
    'decimal amount' => [['amount' => 10.5]],
    'short card number' => [['card' => ['number' => '4111']]],
]);

it('rejects card formats that end with a line feed at the validation rules', function (string $field, string $value) {
    $data = AcquirerMockRequests::authorization();
    data_set($data, $field, $value);

    expect(Validator::make($data, (new AuthorizeRequest)->rules())->errors()->has($field))->toBeTrue();
})->with([
    ['card.number', "4111111111111111\n"],
    ['card.cvv', "123\n"],
]);

it('logs each authorization with the correlation id and without card data', function () {
    $logs = CapturedLogs::install();

    $this->postJson('/acquirer/authorize', AcquirerMockRequests::authorization('4000000000000002'), ['X-Correlation-Id' => 'corr-mock']);

    $line = $logs->first('acquirer_mock.authorized');
    expect($line['extra']['correlation_id'])->toBe('corr-mock')
        ->and($line['context'])->toBe(['transaction_id' => '0199f0c2-6a1e-7c3a-9b2f-5d1e8a4c7b10', 'status_code' => 200])
        ->and($logs->raw())->not->toContain('4000000000000002');
});
