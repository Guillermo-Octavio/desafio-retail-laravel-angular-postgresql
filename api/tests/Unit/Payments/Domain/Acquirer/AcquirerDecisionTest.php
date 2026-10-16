<?php

declare(strict_types=1);

use App\Payments\Domain\Acquirer\AcquirerDecision;
use App\Payments\Domain\AcquirerOutcome;
use App\Payments\Domain\DeclineReason;
use App\Payments\Domain\TransactionStatus;
use Tests\Support\PaymentFixtures;

it('approves with an authorization reference', function () {
    $decision = AcquirerDecision::approved('A1B2C3');

    expect($decision->outcome)->toBe(AcquirerOutcome::APPROVED)
        ->and($decision->authorizationReference)->toBe('A1B2C3')
        ->and($decision->declineReason)->toBeNull();
});

it('declines with a reason', function () {
    $decision = AcquirerDecision::declined(DeclineReason::INSUFFICIENT_FUNDS);

    expect($decision->outcome)->toBe(AcquirerOutcome::DECLINED)
        ->and($decision->declineReason)->toBe(DeclineReason::INSUFFICIENT_FUNDS)
        ->and($decision->authorizationReference)->toBeNull();
});

it('applies an approval to the transaction', function () {
    $transaction = PaymentFixtures::processingTransaction();

    AcquirerDecision::approved('A1B2C3')->applyTo($transaction, PaymentFixtures::at('12:00:02'));

    expect($transaction->status())->toBe(TransactionStatus::APPROVED)
        ->and($transaction->acquirerReference())->toBe('A1B2C3');
});

it('applies a decline to the transaction', function () {
    $transaction = PaymentFixtures::processingTransaction();

    AcquirerDecision::declined(DeclineReason::INSUFFICIENT_FUNDS)->applyTo($transaction, PaymentFixtures::at('12:00:02'));

    expect($transaction->status())->toBe(TransactionStatus::DECLINED)
        ->and($transaction->declineReason())->toBe(DeclineReason::INSUFFICIENT_FUNDS);
});
