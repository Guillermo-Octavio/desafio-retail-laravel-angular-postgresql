<?php

declare(strict_types=1);

use App\Payments\Application\Ports\Sleeper;
use App\Payments\Application\RetryPolicy;
use App\Payments\Domain\DeclineReason;
use App\Payments\Domain\Rules\BusinessRules;
use Illuminate\Support\Sleep;
use Tests\Support\PaymentFixtures;

it('waits the requested milliseconds through Laravel Sleep', function () {
    Sleep::fake();

    app(Sleeper::class)->sleepMilliseconds(200);

    Sleep::assertSequence([Sleep::for(200)->milliseconds()]);
});

it('builds the retry policy from the configuration', function () {
    config(['payments.acquirer.max_attempts' => 2, 'payments.acquirer.initial_backoff_ms' => 300, 'payments.acquirer.max_jitter_ms' => 0]);

    $policy = app(RetryPolicy::class);

    expect($policy->allowsAttempt(2))->toBeTrue()
        ->and($policy->allowsAttempt(3))->toBeFalse()
        ->and($policy->backoffBeforeAttempt(2))->toBe(300);
});

it('builds the business rules with the configured amount limits', function () {
    config(['payments.amount_limits' => ['CLP' => 1000, 'USD' => 500000]]);

    $rules = app(BusinessRules::class);

    expect($rules->firstViolation(PaymentFixtures::paymentRequest(amount: 1000)))->toBeNull()
        ->and($rules->firstViolation(PaymentFixtures::paymentRequest(amount: 1001)))->toBe(DeclineReason::AMOUNT_LIMIT_EXCEEDED);
});
