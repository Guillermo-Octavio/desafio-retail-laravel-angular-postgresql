<?php

declare(strict_types=1);

namespace App\Payments\Domain\Idempotency;

use App\Payments\Domain\Exceptions\InvalidRequestFingerprint;

/**
 * HMAC-SHA256 of the normalized request content, computed outside the domain with the
 * application key so the card number cannot be brute-forced from it (DD-10).
 */
final readonly class RequestFingerprint
{
    private const string SHA256_HEX_FORMAT = '/^[0-9a-f]{64}\z/';

    public function __construct(public string $value)
    {
        if (preg_match(self::SHA256_HEX_FORMAT, $value) !== 1) {
            throw InvalidRequestFingerprint::notSha256Hex();
        }
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }
}
