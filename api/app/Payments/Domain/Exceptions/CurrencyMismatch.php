<?php

declare(strict_types=1);

namespace App\Payments\Domain\Exceptions;

use App\Payments\Domain\Currency;

final class CurrencyMismatch extends PaymentsDomainException
{
    public static function between(Currency $left, Currency $right): self
    {
        return new self("Cannot compare amounts in {$left->value} and {$right->value}.");
    }
}
