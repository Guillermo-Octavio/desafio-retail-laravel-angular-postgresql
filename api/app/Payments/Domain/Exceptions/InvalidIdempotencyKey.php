<?php

declare(strict_types=1);

namespace App\Payments\Domain\Exceptions;

final class InvalidIdempotencyKey extends PaymentsDomainException
{
    public static function invalidLength(int $maxLength): self
    {
        return new self("The idempotency key must have between 1 and {$maxLength} characters.");
    }
}
