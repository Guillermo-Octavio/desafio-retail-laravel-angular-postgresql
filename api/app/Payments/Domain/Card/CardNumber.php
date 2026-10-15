<?php

declare(strict_types=1);

namespace App\Payments\Domain\Card;

use App\Payments\Domain\Exceptions\InvalidCardData;
use App\Payments\Domain\Exceptions\SensitiveDataNotSerializable;
use SensitiveParameter;

/**
 * Transient: the full number only lives in memory during the request and is never
 * persisted, logged or serialized (DD-04).
 */
final readonly class CardNumber
{
    private const string FORMAT = '/^\d{13,19}$/';

    private const int VISIBLE_DIGITS = 4;

    private string $digits;

    public function __construct(#[SensitiveParameter] string $digits)
    {
        if (preg_match(self::FORMAT, $digits) !== 1) {
            throw InvalidCardData::malformedNumber();
        }

        $this->digits = $digits;
    }

    public function reveal(): string
    {
        return $this->digits;
    }

    public function passesLuhn(): bool
    {
        $sum = 0;
        $doubleDigit = false;

        foreach (array_reverse(str_split($this->digits)) as $digit) {
            $value = (int) $digit;

            if ($doubleDigit) {
                $value *= 2;
                $value = $value > 9 ? $value - 9 : $value;
            }

            $sum += $value;
            $doubleDigit = ! $doubleDigit;
        }

        return $sum % 10 === 0;
    }

    public function brand(): CardBrand
    {
        return CardBrand::fromNumber($this->digits);
    }

    public function last4(): string
    {
        return substr($this->digits, -self::VISIBLE_DIGITS);
    }

    public function masked(): MaskedCard
    {
        return new MaskedCard($this->brand(), $this->last4());
    }

    /**
     * @return array{brand: string, last4: string}
     */
    public function __debugInfo(): array
    {
        return ['brand' => $this->brand()->value, 'last4' => $this->last4()];
    }

    /**
     * @return never
     */
    public function __serialize(): array
    {
        throw SensitiveDataNotSerializable::of(self::class);
    }
}
