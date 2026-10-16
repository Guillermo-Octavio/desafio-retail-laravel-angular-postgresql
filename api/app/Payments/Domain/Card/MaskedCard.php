<?php

declare(strict_types=1);

namespace App\Payments\Domain\Card;

use App\Payments\Domain\Exceptions\InvalidCardData;

final readonly class MaskedCard
{
    private const string LAST4_FORMAT = '/^\d{4}\z/';

    public function __construct(
        public CardBrand $brand,
        public string $last4,
    ) {
        if (preg_match(self::LAST4_FORMAT, $last4) !== 1) {
            throw InvalidCardData::malformedLast4();
        }
    }
}
