<?php

declare(strict_types=1);

namespace App\Payments\Http\Resources;

use App\Payments\Domain\Transaction;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public representation of a transaction (HU-01). Only the brand and the last four digits of
 * the card are exposed (HU-11).
 *
 * @property-read Transaction $resource
 */
final class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $transaction = $this->resource;

        return [
            'transaction_id' => $transaction->id()->value,
            'merchant_id' => $transaction->merchantId()->value,
            'amount' => $transaction->money()->amount,
            'currency' => $transaction->money()->currency->value,
            'status' => $transaction->status()->value,
            'decline_reason' => $transaction->declineReason()?->value,
            'card' => [
                'brand' => $transaction->card()->brand->value,
                'last4' => $transaction->card()->last4,
            ],
            'created_at' => $transaction->createdAt()->format(DateTimeInterface::RFC3339_EXTENDED),
            'updated_at' => $transaction->updatedAt()->format(DateTimeInterface::RFC3339_EXTENDED),
        ];
    }
}
