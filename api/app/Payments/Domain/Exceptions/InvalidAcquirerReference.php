<?php

declare(strict_types=1);

namespace App\Payments\Domain\Exceptions;

final class InvalidAcquirerReference extends PaymentsDomainException
{
    public static function invalidLength(int $maxLength): self
    {
        return new self("The acquirer reference must have between 1 and {$maxLength} characters.");
    }
}
