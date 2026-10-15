<?php

declare(strict_types=1);

namespace App\Payments\Domain\Card;

enum CardBrand: string
{
    case VISA = 'VISA';
    case MASTERCARD = 'MASTERCARD';
    case AMEX = 'AMEX';
    case UNKNOWN = 'UNKNOWN';

    public static function fromNumber(string $digits): self
    {
        $twoDigitPrefix = (int) substr($digits, 0, 2);
        $fourDigitPrefix = (int) substr($digits, 0, 4);

        return match (true) {
            str_starts_with($digits, '4') => self::VISA,
            $twoDigitPrefix >= 51 && $twoDigitPrefix <= 55,
            $fourDigitPrefix >= 2221 && $fourDigitPrefix <= 2720 => self::MASTERCARD,
            $twoDigitPrefix === 34 || $twoDigitPrefix === 37 => self::AMEX,
            default => self::UNKNOWN,
        };
    }
}
