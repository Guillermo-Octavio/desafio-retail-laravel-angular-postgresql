<?php

declare(strict_types=1);

namespace App\AcquirerMock;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Str;

/**
 * Decides the simulated answer from the test card (requirements analysis, section 7).
 */
final readonly class AcquirerSimulator
{
    private const string INSUFFICIENT_FUNDS_CARD = '4000000000000002';

    private const string ALWAYS_UNAVAILABLE_CARD = '4000000000000119';

    private const string FAILS_ONCE_CARD = '4000000000000259';

    private const string INSUFFICIENT_FUNDS = 'INSUFFICIENT_FUNDS';

    private const int ATTEMPTS_TTL_SECONDS = 3600;

    private const int AUTHORIZATION_CODE_LENGTH = 6;

    public function __construct(private Cache $cache) {}

    public function authorize(string $cardNumber, string $transactionId): MockResponse
    {
        return match ($cardNumber) {
            self::INSUFFICIENT_FUNDS_CARD => MockResponse::declined(self::INSUFFICIENT_FUNDS),
            self::ALWAYS_UNAVAILABLE_CARD => MockResponse::temporarilyUnavailable(),
            self::FAILS_ONCE_CARD => $this->isFirstAttempt($transactionId)
                ? MockResponse::temporarilyUnavailable()
                : $this->approved(),
            default => $this->approved(),
        };
    }

    private function approved(): MockResponse
    {
        return MockResponse::approved(Str::upper(Str::random(self::AUTHORIZATION_CODE_LENGTH)));
    }

    private function isFirstAttempt(string $transactionId): bool
    {
        $key = "acquirer-mock:attempts:{$transactionId}";
        $this->cache->add($key, 0, self::ATTEMPTS_TTL_SECONDS);

        return $this->cache->increment($key) === 1;
    }
}
