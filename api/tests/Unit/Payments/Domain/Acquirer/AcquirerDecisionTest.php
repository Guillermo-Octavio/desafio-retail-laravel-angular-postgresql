<?php

declare(strict_types=1);

use App\Payments\Domain\Acquirer\AcquirerDecision;
use App\Payments\Domain\AcquirerOutcome;
use App\Payments\Domain\DeclineReason;

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
