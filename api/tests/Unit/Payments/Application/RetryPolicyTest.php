<?php

declare(strict_types=1);

use App\Payments\Application\Exceptions\InvalidRetryPolicy;
use App\Payments\Application\RetryPolicy;
use Tests\Support\PaymentFixtures;

it('allows up to the maximum number of attempts', function () {
    $policy = new RetryPolicy(3, 200, 0, PaymentFixtures::seededRandomizer());

    expect($policy->allowsAttempt(1))->toBeTrue()
        ->and($policy->allowsAttempt(3))->toBeTrue()
        ->and($policy->allowsAttempt(4))->toBeFalse();
});

it('doubles the backoff before each retry', function () {
    $policy = new RetryPolicy(3, 200, 0, PaymentFixtures::seededRandomizer());

    expect($policy->backoffBeforeAttempt(2))->toBe(200)
        ->and($policy->backoffBeforeAttempt(3))->toBe(400);
});

it('adds a bounded random jitter to the backoff', function () {
    $policy = new RetryPolicy(3, 200, 50, PaymentFixtures::seededRandomizer());

    $backoffs = array_map(fn () => $policy->backoffBeforeAttempt(2), range(1, 20));

    expect(min($backoffs))->toBeGreaterThanOrEqual(200)
        ->and(max($backoffs))->toBeLessThanOrEqual(250)
        ->and(array_unique($backoffs))->not->toHaveCount(1);
});

it('waits nothing before the first attempt', function () {
    expect((new RetryPolicy(3, 200, 50, PaymentFixtures::seededRandomizer()))->backoffBeforeAttempt(1))->toBe(0);
});

it('rejects an invalid configuration', function (int $maxAttempts, int $initialBackoffMs, int $maxJitterMs) {
    new RetryPolicy($maxAttempts, $initialBackoffMs, $maxJitterMs, PaymentFixtures::seededRandomizer());
})->with([
    'no attempts' => [0, 200, 50],
    'negative backoff' => [3, -1, 50],
    'negative jitter' => [3, 200, -1],
])->throws(InvalidRetryPolicy::class);
