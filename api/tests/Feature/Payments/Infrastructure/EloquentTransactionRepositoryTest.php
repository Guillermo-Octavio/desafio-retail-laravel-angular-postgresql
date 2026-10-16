<?php

declare(strict_types=1);

use App\Payments\Domain\AcquirerOutcome;
use App\Payments\Domain\DeclineReason;
use App\Payments\Infrastructure\Persistence\EloquentTransactionRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\FixedClock;
use Tests\Support\PaymentFixtures;
use Tests\Support\StoredPayments;

beforeEach(function () {
    $this->repository = new EloquentTransactionRepository(new FixedClock('2026-10-15 12:00:09'));
});

it('stores a new transaction with its creation event', function () {
    $this->repository->save(PaymentFixtures::newTransaction());

    $row = DB::table('transactions')->first();

    expect((array) $row)->toMatchArray([
        'id' => '0199f0c2-6a1e-7c3a-9b2f-5d1e8a4c7b10',
        'merchant_id' => 'merchant-001',
        'amount' => 15000,
        'currency' => 'CLP',
        'status' => 'PENDING',
        'decline_reason' => null,
        'acquirer_reference' => null,
        'card_brand' => 'VISA',
        'card_last4' => '1111',
        'correlation_id' => 'corr-123',
    ])
        ->and(StoredPayments::events())->toBe([[
            'type' => 'STATUS_CHANGED',
            'from_status' => null,
            'to_status' => 'PENDING',
            'reason' => null,
            'metadata' => null,
            'correlation_id' => 'corr-123',
            'occurred_at' => '12:00:00.000001',
        ]]);
});

it('keeps the creation time and records the time of the last save', function () {
    $this->repository->save(PaymentFixtures::newTransaction());

    $times = DB::table('transactions')
        ->selectRaw("to_char(created_at AT TIME ZONE 'UTC', 'HH24:MI:SS.US') as created_at, to_char(updated_at AT TIME ZONE 'UTC', 'HH24:MI:SS.US') as updated_at")
        ->first();

    expect($times->created_at)->toBe('12:00:00.000001')
        ->and($times->updated_at)->toBe('12:00:09.000000');
});

it('updates the state and appends only the new events on each save', function () {
    $transaction = PaymentFixtures::newTransaction();
    $this->repository->save($transaction);

    $transaction->startProcessing(PaymentFixtures::at('12:00:01'));
    $this->repository->save($transaction);

    $transaction->recordAcquirerAttempt(1, AcquirerOutcome::TEMPORARY_ERROR, 2001, 'TIMEOUT', PaymentFixtures::at('12:00:03'));
    $transaction->recordAcquirerAttempt(2, AcquirerOutcome::APPROVED, 85, null, PaymentFixtures::at('12:00:04'));
    $transaction->approve('A1B2C3', PaymentFixtures::at('12:00:04.5'));
    $this->repository->save($transaction);

    expect(DB::table('transactions')->count())->toBe(1)
        ->and(DB::table('transactions')->first(['status', 'acquirer_reference', 'decline_reason']))
        ->toEqual((object) ['status' => 'APPROVED', 'acquirer_reference' => 'A1B2C3', 'decline_reason' => null])
        ->and(array_map(fn (array $event) => [$event['type'], $event['from_status'], $event['to_status'], $event['metadata'], $event['occurred_at']], StoredPayments::events()))
        // jsonb does not keep key order, so the metadata is compared by content.
        ->toEqual([
            ['STATUS_CHANGED', null, 'PENDING', null, '12:00:00.000001'],
            ['STATUS_CHANGED', 'PENDING', 'PROCESSING', null, '12:00:01.000000'],
            ['ACQUIRER_ATTEMPT', null, null, ['attempt' => 1, 'outcome' => 'TEMPORARY_ERROR', 'duration_ms' => 2001, 'error_code' => 'TIMEOUT'], '12:00:03.000000'],
            ['ACQUIRER_ATTEMPT', null, null, ['attempt' => 2, 'outcome' => 'APPROVED', 'duration_ms' => 85], '12:00:04.000000'],
            ['STATUS_CHANGED', 'PROCESSING', 'APPROVED', null, '12:00:04.500000'],
        ]);
});

it('stores the decline reason', function () {
    $transaction = PaymentFixtures::newTransaction();
    $transaction->decline(DeclineReason::AMOUNT_LIMIT_EXCEEDED, PaymentFixtures::at('12:00:01'));

    $this->repository->save($transaction);

    expect(DB::table('transactions')->value('decline_reason'))->toBe('AMOUNT_LIMIT_EXCEEDED')
        ->and(array_column(StoredPayments::events(), 'reason'))->toBe([null, 'AMOUNT_LIMIT_EXCEEDED']);
});

it('adds no events when saving a transaction without changes', function () {
    $transaction = PaymentFixtures::newTransaction();
    $this->repository->save($transaction);
    $this->repository->save($transaction);

    expect(DB::table('transaction_events')->count())->toBe(1);
});

it('saves the state and its events atomically', function () {
    $transaction = PaymentFixtures::newTransaction();
    $this->repository->save($transaction);
    // Forces the event insert to fail. PostgreSQL DDL is transactional, so RefreshDatabase rolls it back.
    DB::statement("ALTER TABLE transaction_events ADD CONSTRAINT test_reject_declined CHECK (to_status IS DISTINCT FROM 'DECLINED')");

    $transaction->decline(DeclineReason::CARD_EXPIRED, PaymentFixtures::at('12:00:01'));

    expect(fn () => $this->repository->save($transaction))->toThrow(QueryException::class)
        ->and(DB::table('transactions')->value('status'))->toBe('PENDING')
        ->and(DB::table('transaction_events')->count())->toBe(1);
});

it('keeps the unsaved events after a failed save so a retry stores them', function () {
    $transaction = PaymentFixtures::newTransaction();
    $this->repository->save($transaction);
    // Forces the event insert to fail. PostgreSQL DDL is transactional, so it can be undone with a savepoint.
    DB::beginTransaction();
    DB::statement("ALTER TABLE transaction_events ADD CONSTRAINT test_reject_declined CHECK (to_status IS DISTINCT FROM 'DECLINED')");
    $transaction->decline(DeclineReason::CARD_EXPIRED, PaymentFixtures::at('12:00:01'));

    expect(fn () => $this->repository->save($transaction))->toThrow(QueryException::class);

    DB::rollBack();
    $this->repository->save($transaction);

    expect(DB::table('transactions')->value('status'))->toBe('DECLINED')
        ->and(array_column(StoredPayments::events(), 'to_status'))->toBe(['PENDING', 'DECLINED'])
        ->and($transaction->recordedEvents())->toBe([]);
});

it('keeps the events when an outer database transaction rolls back', function () {
    $transaction = PaymentFixtures::newTransaction();
    $this->repository->save($transaction);
    $transaction->decline(DeclineReason::CARD_EXPIRED, PaymentFixtures::at('12:00:01'));

    DB::beginTransaction();
    $this->repository->save($transaction);
    DB::rollBack();

    expect($transaction->recordedEvents())->toHaveCount(1)
        ->and(DB::table('transactions')->value('status'))->toBe('PENDING');

    $this->repository->save($transaction);

    expect(array_column(StoredPayments::events(), 'to_status'))->toBe(['PENDING', 'DECLINED'])
        ->and($transaction->recordedEvents())->toBe([]);
});

it('never stores an event twice when saves share an outer transaction that commits', function () {
    $transaction = PaymentFixtures::newTransaction();

    DB::transaction(function () use ($transaction) {
        $this->repository->save($transaction);
        $transaction->startProcessing(PaymentFixtures::at('12:00:01'));
        $this->repository->save($transaction);
        $transaction->fail(DeclineReason::ACQUIRER_UNAVAILABLE, PaymentFixtures::at('12:00:02'));
        $this->repository->save($transaction);
    });

    expect(array_column(StoredPayments::events(), 'to_status'))->toBe(['PENDING', 'PROCESSING', 'FAILED']);
});
