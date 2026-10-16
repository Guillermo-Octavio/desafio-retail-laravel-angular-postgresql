<?php

declare(strict_types=1);

namespace App\Payments\Domain;

use App\Payments\Domain\Exceptions\InvalidTransactionId;

/**
 * UUID v7: not guessable and time-ordered (DD-02). It is generated outside the domain.
 */
final readonly class TransactionId
{
    private const string UUID_V7_FORMAT = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/';

    public string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower($value);

        if (preg_match(self::UUID_V7_FORMAT, $normalized) !== 1) {
            throw InvalidTransactionId::notUuidV7($value);
        }

        $this->value = $normalized;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
