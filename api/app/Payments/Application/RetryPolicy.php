<?php

declare(strict_types=1);

namespace App\Payments\Application;

use App\Payments\Application\Exceptions\InvalidRetryPolicy;
use Random\Randomizer;

/**
 * Retries only temporary acquirer errors, with exponential backoff and jitter so that many
 * clients retrying at once do not hit the acquirer at the same moment (SUP-10).
 */
final readonly class RetryPolicy
{
    private const int BACKOFF_MULTIPLIER = 2;

    public function __construct(
        private int $maxAttempts,
        private int $initialBackoffMs,
        private int $maxJitterMs,
        private Randomizer $randomizer,
    ) {
        if ($maxAttempts < 1) {
            throw InvalidRetryPolicy::nonPositiveAttempts($maxAttempts);
        }

        if ($initialBackoffMs < 0) {
            throw InvalidRetryPolicy::negativeDelay('initial backoff', $initialBackoffMs);
        }

        if ($maxJitterMs < 0) {
            throw InvalidRetryPolicy::negativeDelay('jitter', $maxJitterMs);
        }
    }

    public function allowsAttempt(int $attempt): bool
    {
        return $attempt <= $this->maxAttempts;
    }

    public function backoffBeforeAttempt(int $attempt): int
    {
        if ($attempt <= 1) {
            return 0;
        }

        $backoff = $this->initialBackoffMs * self::BACKOFF_MULTIPLIER ** ($attempt - 2);

        return $backoff + $this->randomizer->getInt(0, $this->maxJitterMs);
    }
}
