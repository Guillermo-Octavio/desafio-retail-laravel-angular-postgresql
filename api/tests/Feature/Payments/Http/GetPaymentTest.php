<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Tests\Support\FakeAcquirer;
use Tests\Support\PaymentRows;

beforeEach(function () {
    FakeAcquirer::install();
});

it('returns the transaction with the same fields as its creation plus its events', function () {
    $created = $this->postJson('/api/payments', PaymentRows::paymentBody(), ['Idempotency-Key' => 'key-001'])->json();

    $response = $this->getJson("/api/payments/{$created['transaction_id']}");

    $response->assertOk();
    expect(collect($response->json())->except('events')->all())->toBe($created);
});

it('lists the history of a payment that needed a retry, in order, with one correlation id', function () {
    $transactionId = $this->postJson(
        '/api/payments',
        PaymentRows::paymentBody(['card.number' => '4000000000000259']),
        ['Idempotency-Key' => 'key-001', 'X-Correlation-Id' => 'corr-history'],
    )->json('transaction_id');

    $events = $this->getJson("/api/payments/{$transactionId}")->json('events');

    expect(array_map(fn (array $event) => [$event['type'], $event['from_status'], $event['to_status'], $event['metadata']['outcome'] ?? null], $events))
        ->toBe([
            ['STATUS_CHANGED', null, 'PENDING', null],
            ['STATUS_CHANGED', 'PENDING', 'PROCESSING', null],
            ['ACQUIRER_ATTEMPT', null, null, 'TEMPORARY_ERROR'],
            ['ACQUIRER_ATTEMPT', null, null, 'APPROVED'],
            ['STATUS_CHANGED', 'PROCESSING', 'APPROVED', null],
        ])
        ->and(array_unique(array_column($events, 'correlation_id')))->toBe(['corr-history'])
        ->and($events[2]['metadata'])->toMatchArray(['attempt' => 1, 'error_code' => 'HTTP_503'])
        ->and($events[0])->toHaveKeys(['reason', 'occurred_at']);
});

it('answers TRANSACTION_NOT_FOUND for an unknown or malformed id', function (string $id) {
    $this->getJson("/api/payments/{$id}")
        ->assertNotFound()
        ->assertExactJson(['error' => ['code' => 'TRANSACTION_NOT_FOUND', 'message' => 'The transaction was not found.']]);
})->with([
    'valid UUID v7 that does not exist' => ['0199f0c2-6a1e-7c3a-9b2f-5d1e8a4c7b10'],
    'nil UUID' => ['00000000-0000-0000-0000-000000000000'],
    'not a UUID' => ['no-es-un-uuid'],
]);

it('shows the event times in Santiago time too', function () {
    Carbon::setTestNow('2026-10-15 12:00:00');
    $transactionId = $this->postJson('/api/payments', PaymentRows::paymentBody(), ['Idempotency-Key' => 'key-001'])->json('transaction_id');

    $events = $this->getJson("/api/payments/{$transactionId}")->json('events');

    expect(array_unique(array_column($events, 'occurred_at')))->toBe(['2026-10-15T09:00:00.000-03:00']);
});
