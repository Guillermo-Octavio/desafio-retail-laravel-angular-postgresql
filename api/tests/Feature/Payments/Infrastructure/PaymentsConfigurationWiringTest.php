<?php

declare(strict_types=1);

use App\Payments\Application\BusinessTimezone;
use App\Payments\Application\Ports\Sleeper;
use App\Payments\Application\RetryPolicy;
use App\Payments\Domain\DeclineReason;
use App\Payments\Domain\Rules\BusinessRules;
use App\Payments\Infrastructure\Exceptions\InvalidBusinessTimezone;
use App\Payments\Infrastructure\PaymentsServiceProvider;
use Illuminate\Support\Carbon;
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

it('uses America/Santiago as the business timezone by default', function () {
    expect(config('payments.business_timezone'))->toBe('America/Santiago');
});

it('evaluates card expiry in the configured business timezone', function () {
    Carbon::setTestNow('2026-11-01 01:00:00');

    $santiago = app(BusinessRules::class)->firstViolation(PaymentFixtures::paymentRequest(expiryMonth: 10, expiryYear: 2026));
    app()->forgetInstance(BusinessRules::class);
    app()->forgetInstance(BusinessTimezone::class);
    config(['payments.business_timezone' => 'UTC']);
    $utc = app(BusinessRules::class)->firstViolation(PaymentFixtures::paymentRequest(expiryMonth: 10, expiryYear: 2026));

    expect($santiago)->toBeNull()
        ->and($utc)->toBe(DeclineReason::CARD_EXPIRED);
});

it('shares a single business timezone built from the configuration', function () {
    $timezone = app(BusinessTimezone::class);

    expect($timezone->zone->getName())->toBe('America/Santiago')
        ->and(app(BusinessTimezone::class))->toBe($timezone);
});

it('refuses to start with a business timezone that does not exist', function (string $timezone) {
    config(['payments.business_timezone' => $timezone]);

    (new PaymentsServiceProvider(app()))->boot();
})->with(['America/Santigo', ''])->throws(InvalidBusinessTimezone::class);

it('starts with any timezone PHP accepts, including aliases and offsets', function (string $timezone) {
    config(['payments.business_timezone' => $timezone]);
    app()->forgetInstance(BusinessTimezone::class);

    (new PaymentsServiceProvider(app()))->boot();

    expect(app(BusinessTimezone::class)->zone)->toBeInstanceOf(DateTimeZone::class);
})->with(['Etc/UTC', 'GMT', 'US/Pacific', '-03:00', 'america/santiago']);
