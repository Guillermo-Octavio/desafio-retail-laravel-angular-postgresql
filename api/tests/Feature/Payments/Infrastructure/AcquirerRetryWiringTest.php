<?php

declare(strict_types=1);

use App\Payments\Application\Ports\Sleeper;
use App\Payments\Application\RetryPolicy;
use Illuminate\Support\Sleep;

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
