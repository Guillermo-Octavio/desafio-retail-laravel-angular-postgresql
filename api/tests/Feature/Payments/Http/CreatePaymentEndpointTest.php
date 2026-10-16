<?php

declare(strict_types=1);

use App\Payments\Application\Exceptions\IdempotencyKeyInProgress;
use App\Payments\Application\Exceptions\IdempotencyKeyReused;
use App\Payments\Http\Exceptions\MissingIdempotencyKey;
use App\Payments\Http\Requests\CreatePaymentRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\Support\FakeAcquirer;
use Tests\Support\PaymentRows;

beforeEach(function () {
    $this->acquirer = FakeAcquirer::install();
});

it('creates a payment and answers 201 with its final state', function () {
    $response = $this->postJson('/api/payments', PaymentRows::paymentBody(), ['Idempotency-Key' => 'key-001']);

    $response->assertCreated()
        ->assertHeaderMissing('Idempotent-Replayed')
        ->assertJsonPath('merchant_id', 'merchant-001')
        ->assertJsonPath('amount', 15000)
        ->assertJsonPath('currency', 'CLP')
        ->assertJsonPath('status', 'APPROVED')
        ->assertJsonPath('decline_reason', null)
        ->assertJsonPath('card', ['brand' => 'VISA', 'last4' => '1111'])
        ->assertJsonStructure(['transaction_id', 'created_at', 'updated_at']);
    expect(Str::isUuid($response->json('transaction_id')))->toBeTrue()
        ->and(DB::table('transactions')->where('merchant_id', 'merchant-001')->count())->toBe(1);
});

it('returns the dates in ISO 8601 in Santiago time, with the offset of each season', function (string $utcNow, string $expected) {
    Carbon::setTestNow($utcNow);

    $response = $this->postJson('/api/payments', PaymentRows::paymentBody(), ['Idempotency-Key' => 'key-001']);

    expect($response->json('created_at'))->toBe($expected)
        ->and($response->json('updated_at'))->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}-0[34]:00$/');
})->with([
    'summer time, UTC-3' => ['2026-10-15 12:00:00', '2026-10-15T09:00:00.000-03:00'],
    'winter time, UTC-4' => ['2026-06-15 12:00:00', '2026-06-15T08:00:00.000-04:00'],
]);

it('keeps storing the instant in UTC', function () {
    Carbon::setTestNow('2026-10-15 12:00:00');

    $this->postJson('/api/payments', PaymentRows::paymentBody(), ['Idempotency-Key' => 'key-001']);

    expect(DB::selectOne("select to_char(created_at at time zone 'UTC', 'YYYY-MM-DD HH24:MI:SS') as created_at from transactions")->created_at)
        ->toBe('2026-10-15 12:00:00');
});

it('follows the configured business timezone', function () {
    Carbon::setTestNow('2026-10-15 12:00:00');
    config(['payments.business_timezone' => 'UTC']);

    $response = $this->postJson('/api/payments', PaymentRows::paymentBody(), ['Idempotency-Key' => 'key-001']);

    expect($response->json('created_at'))->toBe('2026-10-15T12:00:00.000+00:00');
});

it('requires the Idempotency-Key header before validating the body', function (array $headers) {
    $this->postJson('/api/payments', ['merchant_id' => null], $headers)
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_MISSING');

    expect(DB::table('transactions')->count())->toBe(0)
        ->and(DB::table('idempotency_keys')->count())->toBe(0);
})->with([
    'missing' => [[]],
    'blank' => [['Idempotency-Key' => '   ']],
]);

it('rejects an Idempotency-Key longer than 255 characters', function () {
    $this->postJson('/api/payments', PaymentRows::paymentBody(), ['Idempotency-Key' => str_repeat('k', 256)])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonValidationErrorFor('idempotency_key', 'error.details');
});

it('rejects an invalid field without creating a transaction or reserving the key', function (string $field, mixed $value) {
    $this->postJson('/api/payments', PaymentRows::paymentBody([$field => $value]), ['Idempotency-Key' => 'key-001'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonValidationErrorFor($field, 'error.details');

    expect(DB::table('transactions')->count())->toBe(0)
        ->and(DB::table('idempotency_keys')->count())->toBe(0)
        ->and($this->acquirer->calls())->toBe(0);
})->with([
    ['merchant_id', null],
    ['merchant_id', str_repeat('m', 65)],
    'merchant_id with a NUL character' => ['merchant_id', "merchant-a\u{0000}b"],
    'merchant_id with a line break' => ['merchant_id', "merchant\n001"],
    'holder_name with a NUL character' => ['card.holder_name', "Ada\u{0000}Lovelace"],
    ['amount', 0],
    ['amount', -100],
    ['amount', 10.50],
    ['amount', '1050'],
    ['currency', 'EUR'],
    ['card.number', '4111'],
    ['card.number', '4111abcd11111111'],
    ['card.holder_name', null],
    ['card.expiry_month', 13],
    ['card.expiry_month', '12'],
    ['card.expiry_year', 30],
    ['card.cvv', '12'],
    ['card.cvv', 123],
]);

it('rejects an Idempotency-Key that is not valid UTF-8 or has control characters', function (string $key) {
    $this->postJson('/api/payments', PaymentRows::paymentBody(), ['Idempotency-Key' => $key])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('idempotency_key', 'error.details');

    expect(DB::table('idempotency_keys')->count())->toBe(0);
})->with([
    'invalid UTF-8' => ["key-\xff\xfe-001"],
    'tab character' => ["key\t001"],
]);

it('reports several invalid fields at once', function () {
    $body = PaymentRows::paymentBody();
    unset($body['amount'], $body['currency']);

    $this->postJson('/api/payments', $body, ['Idempotency-Key' => 'key-001'])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('amount', 'error.details')
        ->assertJsonValidationErrorFor('currency', 'error.details');
});

it('replays the original response with the Idempotent-Replayed header', function () {
    $original = $this->postJson('/api/payments', PaymentRows::paymentBody(), ['Idempotency-Key' => 'key-001']);

    $replay = $this->postJson('/api/payments', PaymentRows::paymentBody(), ['Idempotency-Key' => 'key-001']);

    $replay->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
    expect($replay->getContent())->toBe($original->getContent());
});

it('does not report expected client errors as application errors', function () {
    Exceptions::fake();

    $this->postJson('/api/payments', PaymentRows::paymentBody());
    $this->postJson('/api/payments', PaymentRows::paymentBody(['amount' => 15000]), ['Idempotency-Key' => 'key-001']);
    $this->postJson('/api/payments', PaymentRows::paymentBody(['amount' => 20000]), ['Idempotency-Key' => 'key-001']);

    DB::table('idempotency_keys')->insert(PaymentRows::inProgressKeyForBaseBody('key-in-progress'));
    $this->postJson('/api/payments', PaymentRows::paymentBody(), ['Idempotency-Key' => 'key-in-progress'])
        ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_IN_PROGRESS');

    Exceptions::assertNotReported(MissingIdempotencyKey::class);
    Exceptions::assertNotReported(IdempotencyKeyReused::class);
    Exceptions::assertNotReported(IdempotencyKeyInProgress::class);
});

it('rejects a text field that ends with a line feed at the validation rules', function (string $field, string $value) {
    $request = new CreatePaymentRequest;
    $data = PaymentRows::paymentBody(['idempotency_key' => 'key-001', $field => $value]);

    expect(Validator::make($data, $request->rules())->errors()->has($field))->toBeTrue();
})->with([
    ['merchant_id', "merchant-001\n"],
    ['card.number', "4111111111111111\n"],
    ['card.holder_name', "Ada Lovelace\n"],
    ['card.cvv', "123\n"],
    ['idempotency_key', "key-001\n"],
]);

it('trims a trailing line feed from the body before the card data reaches the acquirer', function () {
    $this->postJson('/api/payments', PaymentRows::paymentBody(['card.number' => "4111111111111111\n", 'card.cvv' => "123\n"]), ['Idempotency-Key' => 'key-001'])
        ->assertCreated();

    Http::assertSent(fn (Request $request) => $request['card']['number'] === '4111111111111111' && $request['card']['cvv'] === '123');
});
