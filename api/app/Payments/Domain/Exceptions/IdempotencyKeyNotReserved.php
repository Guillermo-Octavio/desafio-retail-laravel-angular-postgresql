<?php

declare(strict_types=1);

namespace App\Payments\Domain\Exceptions;

/**
 * The key values are left out of the message: they come from the merchant and end up in logs.
 */
final class IdempotencyKeyNotReserved extends PaymentsDomainException
{
    public static function forMerchantKey(): self
    {
        return new self('No idempotency key is reserved for this merchant and key.');
    }

    public static function conflictNotVisible(): self
    {
        return new self('The idempotency key already exists but its record could not be read.');
    }
}
