<?php

declare(strict_types=1);

use App\Payments\Domain\AcquirerOutcome;
use App\Payments\Domain\Currency;
use App\Payments\Domain\DeclineReason;
use App\Payments\Domain\EventType;
use App\Payments\Domain\Exceptions\InvalidAcquirerAttempt;
use App\Payments\Domain\Exceptions\InvalidAcquirerReference;
use App\Payments\Domain\Exceptions\InvalidTransactionTransition;
use App\Payments\Domain\Money;
use App\Payments\Domain\Transaction;
use App\Payments\Domain\TransactionEvent;
use App\Payments\Domain\TransactionStatus;
use Tests\Support\PaymentFixtures;

it('is created in PENDING with its data and a creation event', function () {
    $transaction = PaymentFixtures::newTransaction();
    $events = $transaction->recordedEvents();

    expect($transaction->id()->value)->toBe('0199f0c2-6a1e-7c3a-9b2f-5d1e8a4c7b10')
        ->and($transaction->merchantId()->value)->toBe('merchant-001')
        ->and($transaction->money()->equals(new Money(15000, Currency::CLP)))->toBeTrue()
        ->and($transaction->card()->last4)->toBe('1111')
        ->and($transaction->status())->toBe(TransactionStatus::PENDING)
        ->and($transaction->declineReason())->toBeNull()
        ->and($transaction->acquirerReference())->toBeNull()
        ->and($transaction->correlationId()->value)->toBe('corr-123')
        ->and($transaction->createdAt())->toEqual(PaymentFixtures::at('12:00:00.000001'))
        ->and($events)->toHaveCount(1)
        ->and($events[0]->type)->toBe(EventType::STATUS_CHANGED)
        ->and($events[0]->fromStatus)->toBeNull()
        ->and($events[0]->toStatus)->toBe(TransactionStatus::PENDING)
        ->and($events[0]->correlationId->value)->toBe('corr-123')
        ->and($events[0]->occurredAt)->toEqual(PaymentFixtures::at('12:00:00.000001'));
});

it('is declined by a business rule while PENDING', function () {
    $transaction = PaymentFixtures::newTransaction();
    $transaction->clearRecordedEvents();

    $transaction->decline(DeclineReason::AMOUNT_LIMIT_EXCEEDED, PaymentFixtures::at('12:00:01'));

    expect($transaction->status())->toBe(TransactionStatus::DECLINED)
        ->and($transaction->declineReason())->toBe(DeclineReason::AMOUNT_LIMIT_EXCEEDED)
        ->and(PaymentFixtures::describeEvents($transaction->recordedEvents()))->toBe([[
            'type' => EventType::STATUS_CHANGED,
            'from' => TransactionStatus::PENDING,
            'to' => TransactionStatus::DECLINED,
            'reason' => DeclineReason::AMOUNT_LIMIT_EXCEEDED,
            'metadata' => [],
        ]]);
});

it('records the history of a payment approved after a retry', function () {
    $transaction = PaymentFixtures::newTransaction();

    $transaction->startProcessing(PaymentFixtures::at('12:00:01'));
    $transaction->recordAcquirerAttempt(1, AcquirerOutcome::TEMPORARY_ERROR, 2001, 'TIMEOUT', PaymentFixtures::at('12:00:03'));
    $transaction->recordAcquirerAttempt(2, AcquirerOutcome::APPROVED, 85, null, PaymentFixtures::at('12:00:04'));
    $transaction->approve('A1B2C3', PaymentFixtures::at('12:00:04'));

    expect($transaction->status())->toBe(TransactionStatus::APPROVED)
        ->and($transaction->acquirerReference())->toBe('A1B2C3')
        ->and($transaction->declineReason())->toBeNull()
        ->and(PaymentFixtures::describeEvents($transaction->recordedEvents()))->toBe([
            ['type' => EventType::STATUS_CHANGED, 'from' => null, 'to' => TransactionStatus::PENDING, 'reason' => null, 'metadata' => []],
            ['type' => EventType::STATUS_CHANGED, 'from' => TransactionStatus::PENDING, 'to' => TransactionStatus::PROCESSING, 'reason' => null, 'metadata' => []],
            ['type' => EventType::ACQUIRER_ATTEMPT, 'from' => null, 'to' => null, 'reason' => null, 'metadata' => ['attempt' => 1, 'outcome' => 'TEMPORARY_ERROR', 'duration_ms' => 2001, 'error_code' => 'TIMEOUT']],
            ['type' => EventType::ACQUIRER_ATTEMPT, 'from' => null, 'to' => null, 'reason' => null, 'metadata' => ['attempt' => 2, 'outcome' => 'APPROVED', 'duration_ms' => 85]],
            ['type' => EventType::STATUS_CHANGED, 'from' => TransactionStatus::PROCESSING, 'to' => TransactionStatus::APPROVED, 'reason' => null, 'metadata' => []],
        ]);
});

it('is declined by the acquirer while PROCESSING', function () {
    $transaction = PaymentFixtures::processingTransaction();

    $transaction->decline(DeclineReason::INSUFFICIENT_FUNDS, PaymentFixtures::at('12:00:02'));

    expect($transaction->status())->toBe(TransactionStatus::DECLINED)
        ->and($transaction->declineReason())->toBe(DeclineReason::INSUFFICIENT_FUNDS)
        ->and($transaction->recordedEvents()[0]->fromStatus)->toBe(TransactionStatus::PROCESSING);
});

it('fails with a reason while PROCESSING', function () {
    $transaction = PaymentFixtures::processingTransaction();

    $transaction->fail(DeclineReason::ACQUIRER_UNAVAILABLE, PaymentFixtures::at('12:00:07'));
    $events = $transaction->recordedEvents();

    expect($transaction->status())->toBe(TransactionStatus::FAILED)
        ->and($transaction->declineReason())->toBe(DeclineReason::ACQUIRER_UNAVAILABLE)
        ->and($transaction->acquirerReference())->toBeNull()
        ->and($events[0]->toStatus)->toBe(TransactionStatus::FAILED)
        ->and($events[0]->reason)->toBe(DeclineReason::ACQUIRER_UNAVAILABLE);
});

it('keeps the recorded events until they are cleared', function () {
    $transaction = PaymentFixtures::newTransaction();

    expect($transaction->recordedEvents())->toHaveCount(1)
        ->and($transaction->recordedEvents())->toHaveCount(1);

    $transaction->clearRecordedEvents();

    expect($transaction->recordedEvents())->toBe([]);
});

it('rejects a transition outside the state machine without changing the transaction', function (Closure $prepare, Closure $operation) {
    $transaction = PaymentFixtures::newTransaction();
    $prepare($transaction);
    $transaction->clearRecordedEvents();
    $statusBefore = $transaction->status();
    $reasonBefore = $transaction->declineReason();
    $referenceBefore = $transaction->acquirerReference();

    try {
        $operation($transaction);
    } catch (InvalidTransactionTransition) {
        expect($transaction->status())->toBe($statusBefore)
            ->and($transaction->declineReason())->toBe($reasonBefore)
            ->and($transaction->acquirerReference())->toBe($referenceBefore)
            ->and($transaction->recordedEvents())->toBe([]);

        return;
    }

    test()->fail('InvalidTransactionTransition was not thrown.');
})->with([
    'approve while PENDING' => [fn () => null, fn (Transaction $t) => $t->approve('A1B2C3', PaymentFixtures::at('12:00:02'))],
    'fail while PENDING' => [fn () => null, fn (Transaction $t) => $t->fail(DeclineReason::ACQUIRER_UNAVAILABLE, PaymentFixtures::at('12:00:02'))],
    'attempt while PENDING' => [fn () => null, fn (Transaction $t) => $t->recordAcquirerAttempt(1, AcquirerOutcome::APPROVED, 10, null, PaymentFixtures::at('12:00:02'))],
    'start twice' => [fn (Transaction $t) => $t->startProcessing(PaymentFixtures::at('12:00:01')), fn (Transaction $t) => $t->startProcessing(PaymentFixtures::at('12:00:02'))],
    'approve after APPROVED' => [function (Transaction $t) {
        $t->startProcessing(PaymentFixtures::at('12:00:01'));
        $t->approve('A1B2C3', PaymentFixtures::at('12:00:02'));
    }, fn (Transaction $t) => $t->approve('Z9', PaymentFixtures::at('12:00:03'))],
    'decline after APPROVED' => [function (Transaction $t) {
        $t->startProcessing(PaymentFixtures::at('12:00:01'));
        $t->approve('A1B2C3', PaymentFixtures::at('12:00:02'));
    }, fn (Transaction $t) => $t->decline(DeclineReason::INSUFFICIENT_FUNDS, PaymentFixtures::at('12:00:03'))],
    'start after DECLINED' => [fn (Transaction $t) => $t->decline(DeclineReason::CARD_EXPIRED, PaymentFixtures::at('12:00:01')), fn (Transaction $t) => $t->startProcessing(PaymentFixtures::at('12:00:02'))],
    'attempt after FAILED' => [function (Transaction $t) {
        $t->startProcessing(PaymentFixtures::at('12:00:01'));
        $t->fail(DeclineReason::ACQUIRER_UNAVAILABLE, PaymentFixtures::at('12:00:02'));
    }, fn (Transaction $t) => $t->recordAcquirerAttempt(4, AcquirerOutcome::APPROVED, 10, null, PaymentFixtures::at('12:00:03'))],
]);

it('requires an acquirer reference of 1 to 64 characters to approve', function (string $reference) {
    PaymentFixtures::processingTransaction()->approve($reference, PaymentFixtures::at('12:00:02'));
})->with(['', str_repeat('R', 65)])->throws(InvalidAcquirerReference::class);

it('requires a positive attempt number and a non-negative duration', function (int $attempt, int $durationMs) {
    PaymentFixtures::processingTransaction()->recordAcquirerAttempt($attempt, AcquirerOutcome::APPROVED, $durationMs, null, PaymentFixtures::at('12:00:02'));
})->with([
    'attempt zero' => [0, 10],
    'negative duration' => [1, -1],
])->throws(InvalidAcquirerAttempt::class);

it('keeps numbering its events after the recorded ones are cleared', function () {
    $transaction = PaymentFixtures::newTransaction();
    $transaction->clearRecordedEvents();
    $transaction->startProcessing(PaymentFixtures::at('12:00:01'));
    $transaction->recordAcquirerAttempt(1, AcquirerOutcome::APPROVED, 85, null, PaymentFixtures::at('12:00:02'));
    $transaction->approve('A1B2C3', PaymentFixtures::at('12:00:02'));

    expect(array_map(fn (TransactionEvent $event) => $event->sequence, $transaction->recordedEvents()))->toBe([2, 3, 4]);
});
