<?php

declare(strict_types=1);

namespace App\Payments\Application;

use App\Payments\Domain\Card\CardDetails;
use App\Payments\Domain\CorrelationId;
use App\Payments\Domain\Idempotency\IdempotencyKey;
use App\Payments\Domain\MerchantId;
use App\Payments\Domain\Money;

/**
 * A validated payment request. The card data only lives in memory (DD-04).
 */
final readonly class ProcessPaymentCommand
{
    public function __construct(
        public MerchantId $merchantId,
        public IdempotencyKey $idempotencyKey,
        public CorrelationId $correlationId,
        public Money $money,
        public CardDetails $card,
        public string $cardHolderName,
    ) {}
}
