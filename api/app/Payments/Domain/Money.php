<?php

declare(strict_types=1);

namespace App\Payments\Domain;

use App\Payments\Domain\Exceptions\CurrencyMismatch;
use App\Payments\Domain\Exceptions\InvalidAmount;

final readonly class Money
{
    public function __construct(
        public int $amount,
        public Currency $currency,
    ) {
        if ($amount <= 0) {
            throw InvalidAmount::mustBePositive($amount);
        }
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    public function isGreaterThan(self $other): bool
    {
        if ($this->currency !== $other->currency) {
            throw CurrencyMismatch::between($this->currency, $other->currency);
        }

        return $this->amount > $other->amount;
    }
}
