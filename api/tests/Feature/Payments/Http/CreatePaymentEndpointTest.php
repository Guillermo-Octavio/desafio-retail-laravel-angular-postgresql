<?php

declare(strict_types=1);

use App\Payments\Application\Exceptions\IdempotencyKeyInProgress;
use App\Payments\Application\Exceptions\IdempotencyKeyReused;
use App\Payments\Http\Exceptions\MissingIdempotencyKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
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

it('returns the dates in ISO 8601 with milliseconds', function () {
    $response = $this->postJson('/api/payments', PaymentRows::paymentBody(), ['Idempotency-Key' => 'key-001']);

    expect($response->json('created_at'))->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}\+00:00$/')
        ->and($response->json('updated_at'))->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}\+00:00$/');
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

    DB::table('idempotency_keys')->insert(PaymentRows::idempotencyKey(['idempotency_key' => 'key-in-progress']));
    $this->postJson('/api/payments', PaymentRows::paymentBody(), ['Idempotency-Key' => 'key-in-progress']);

    Exceptions::assertNotReported(MissingIdempotencyKey::class);
    Exceptions::assertNotReported(IdempotencyKeyReused::class);
    Exceptions::assertNotReported(IdempotencyKeyInProgress::class);
});
