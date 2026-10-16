<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Tests\Support\FakeAcquirer;
use Tests\Support\PaymentRows;

beforeEach(function () {
    FakeAcquirer::install();
    $this->idsByName = [];

    $payments = [
        'approved-1' => ['merchant-001', '4111111111111111'],
        'declined-1' => ['merchant-001', '4000000000000002'],
        'failed-1' => ['merchant-001', '4000000000000119'],
        'approved-2' => ['merchant-002', '4111111111111111'],
    ];

    $minute = 0;
    foreach ($payments as $name => [$merchantId, $cardNumber]) {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00')->addMinutes($minute++));
        $this->idsByName[$name] = $this->postJson(
            '/api/payments',
            PaymentRows::paymentBody(['merchant_id' => $merchantId, 'card.number' => $cardNumber]),
            ['Idempotency-Key' => "key-{$name}"],
        )->json('transaction_id');
    }
});

it('filters by merchant and status together', function () {
    $response = $this->getJson('/api/payments?merchant_id=merchant-001&status=APPROVED')->assertOk();

    expect(array_column($response->json('data'), 'transaction_id'))->toBe([$this->idsByName['approved-1']]);
});

it('filters by merchant only, newest first', function () {
    $response = $this->getJson('/api/payments?merchant_id=merchant-001')->assertOk();

    expect(array_column($response->json('data'), 'transaction_id'))
        ->toBe([$this->idsByName['failed-1'], $this->idsByName['declined-1'], $this->idsByName['approved-1']]);
});

it('lists every transaction without filters, each with the creation fields and no events', function () {
    $response = $this->getJson('/api/payments')->assertOk();

    expect($response->json('data'))->toHaveCount(4)
        ->and($response->json('data.0'))->toHaveKeys(['transaction_id', 'merchant_id', 'amount', 'currency', 'status', 'decline_reason', 'card', 'created_at', 'updated_at'])
        ->and($response->json('data.0'))->not->toHaveKey('events')
        ->and($response->json('meta'))->toBe(['current_page' => 1, 'per_page' => 20, 'total' => 4, 'last_page' => 1]);
});

it('answers an empty list when nothing matches', function () {
    $this->getJson('/api/payments?merchant_id=merchant-999')
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.total', 0);
});

it('rejects an unknown status filter', function () {
    $this->getJson('/api/payments?status=PAID')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonValidationErrorFor('status', 'error.details');
});

it('caps the page size at 100', function () {
    $this->getJson('/api/payments?per_page=500')->assertOk()->assertJsonPath('meta.per_page', 100);
});

it('pages through the results', function () {
    $response = $this->getJson('/api/payments?per_page=2&page=2')->assertOk();

    expect(array_column($response->json('data'), 'transaction_id'))
        ->toBe([$this->idsByName['declined-1'], $this->idsByName['approved-1']])
        ->and($response->json('meta'))->toBe(['current_page' => 2, 'per_page' => 2, 'total' => 4, 'last_page' => 2]);
});

it('rejects invalid paging values', function (string $query, string $field) {
    $this->getJson("/api/payments?{$query}")
        ->assertStatus(422)
        ->assertJsonValidationErrorFor($field, 'error.details');
})->with([
    ['page=0', 'page'],
    ['per_page=0', 'per_page'],
    ['per_page=abc', 'per_page'],
]);
