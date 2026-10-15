<?php

declare(strict_types=1);

namespace App\Payments\Domain\Card;

use App\Payments\Domain\Exceptions\InvalidCardData;

final readonly class MaskedCard
{
    public function __construct(
        public CardBrand $brand,
        public string $last4,
    ) {
        if (preg_match('/^\d{4}$/', $last4) !== 1) {
            throw InvalidCardData::malformedLast4();
        }
    }
}
