<?php

declare(strict_types=1);

namespace App\Payments\Domain\Exceptions;

final class InvalidMerchantId extends PaymentsDomainException
{
    public static function invalidLength(int $maxLength): self
    {
        return new self("The merchant id must have between 1 and {$maxLength} characters.");
    }
}
