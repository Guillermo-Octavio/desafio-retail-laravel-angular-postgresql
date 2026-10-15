<?php

declare(strict_types=1);

namespace App\Payments\Domain;

enum Currency: string
{
    case CLP = 'CLP';
    case USD = 'USD';

    public function exponent(): int
    {
        return match ($this) {
            self::CLP => 0,
            self::USD => 2,
        };
    }
}
