<?php

declare(strict_types=1);

namespace App\Payments\Domain\Acquirer;

use App\Payments\Domain\Card\CardDetails;
use App\Payments\Domain\CorrelationId;
use App\Payments\Domain\MerchantId;
use App\Payments\Domain\Money;
use App\Payments\Domain\TransactionId;

/**
 * Carries the full card data to the acquirer; it only lives in memory (DD-04).
 */
final readonly class AcquirerRequest
{
    public function __construct(
        public TransactionId $transactionId,
        public MerchantId $merchantId,
        public Money $money,
        public CardDetails $card,
        public CorrelationId $correlationId,
    ) {}
}
