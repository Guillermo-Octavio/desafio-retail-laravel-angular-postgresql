<?php

declare(strict_types=1);

namespace App\Payments\Domain\Exceptions;

final class InvalidAmount extends PaymentsDomainException
{
    public static function mustBePositive(int $amount): self
    {
        return new self("The amount must be a positive integer in minor units, {$amount} given.");
    }
}
