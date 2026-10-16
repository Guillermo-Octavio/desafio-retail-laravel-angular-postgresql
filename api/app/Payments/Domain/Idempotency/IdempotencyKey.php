<?php

declare(strict_types=1);

namespace App\Payments\Domain\Idempotency;

use App\Payments\Domain\Exceptions\InvalidIdempotencyKey;

/**
 * Scoped to the merchant: two merchants may use the same key (SUP-11).
 */
final readonly class IdempotencyKey
{
    private const int MAX_LENGTH = 255;

    public function __construct(public string $value)
    {
        $length = mb_strlen($value);

        if ($length === 0 || $length > self::MAX_LENGTH) {
            throw InvalidIdempotencyKey::invalidLength(self::MAX_LENGTH);
        }
    }
}
