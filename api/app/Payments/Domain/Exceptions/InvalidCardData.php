<?php

declare(strict_types=1);

namespace App\Payments\Domain\Exceptions;

/**
 * Messages never include the offending value: card data must not reach logs (HU-11).
 */
final class InvalidCardData extends PaymentsDomainException
{
    public static function malformedNumber(): self
    {
        return new self('The card number must have between 13 and 19 digits.');
    }

    public static function malformedCvv(): self
    {
        return new self('The CVV must have 3 or 4 digits.');
    }

    public static function invalidExpiryMonth(int $month): self
    {
        return new self("The expiry month must be between 1 and 12, {$month} given.");
    }

    public static function invalidExpiryYear(int $year): self
    {
        return new self("The expiry year must have four digits, {$year} given.");
    }

    public static function malformedLast4(): self
    {
        return new self('The last four digits of the card must be exactly 4 digits.');
    }
}
