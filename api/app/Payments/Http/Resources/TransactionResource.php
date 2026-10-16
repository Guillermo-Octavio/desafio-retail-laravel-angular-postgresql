<?php

declare(strict_types=1);

namespace App\Payments\Http\Resources;

use App\Payments\Application\Queries\TransactionSummary;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public representation of a transaction (HU-01). Only the brand and the last four digits of
 * the card are exposed (HU-11).
 *
 * @property-read TransactionSummary $resource
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
            'transaction_id' => $transaction->transactionId,
            'merchant_id' => $transaction->merchantId,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'status' => $transaction->status,
            'decline_reason' => $transaction->declineReason,
            'card' => [
                'brand' => $transaction->cardBrand,
                'last4' => $transaction->cardLast4,
            ],
            'created_at' => $transaction->createdAt->format(DateTimeInterface::RFC3339_EXTENDED),
            'updated_at' => $transaction->updatedAt->format(DateTimeInterface::RFC3339_EXTENDED),
        ];
    }
}
