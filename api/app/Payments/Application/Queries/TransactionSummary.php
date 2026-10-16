<?php

declare(strict_types=1);

namespace App\Payments\Application\Queries;

use App\Payments\Domain\Transaction;
use DateTimeImmutable;

/**
 * Read model of a transaction: what the API shows for one item, without its history.
 */
final readonly class TransactionSummary
{
    public function __construct(
        public string $transactionId,
        public string $merchantId,
        public int $amount,
        public string $currency,
        public string $status,
        public ?string $declineReason,
        public string $cardBrand,
        public string $cardLast4,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}

    public static function fromTransaction(Transaction $transaction): self
    {
        return new self(
            $transaction->id()->value,
            $transaction->merchantId()->value,
            $transaction->money()->amount,
            $transaction->money()->currency->value,
            $transaction->status()->value,
            $transaction->declineReason()?->value,
            $transaction->card()->brand->value,
            $transaction->card()->last4,
            $transaction->createdAt(),
            $transaction->updatedAt(),
        );
    }
}
