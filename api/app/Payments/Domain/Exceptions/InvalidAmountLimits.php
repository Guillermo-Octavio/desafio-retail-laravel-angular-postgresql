<?php

declare(strict_types=1);

namespace App\Payments\Domain\Exceptions;

final class InvalidAmountLimits extends PaymentsDomainException
{
    public static function missingFor(string $currency): self
    {
        return new self("A positive integer amount limit is required for {$currency}.");
    }

    public static function unsupportedCurrency(string $currency): self
    {
        return new self("Cannot set an amount limit for the unsupported currency {$currency}.");
    }
}
