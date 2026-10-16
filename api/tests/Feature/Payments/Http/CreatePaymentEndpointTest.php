<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
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

    $replay->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true')
        ->assertExactJson($original->json());
});
