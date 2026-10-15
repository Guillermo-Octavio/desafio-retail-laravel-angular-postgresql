<?php

declare(strict_types=1);

namespace App\Payments\Domain;

use App\Payments\Domain\Card\CardDetails;

final readonly class PaymentRequest
{
    public function __construct(
        public MerchantId $merchantId,
        public Money $money,
        public CardDetails $card,
    ) {}
}
