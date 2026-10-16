<?php

declare(strict_types=1);

namespace App\Payments\Http\Resources;

use App\Payments\Application\Ports\PaymentResponsePresenter;
use App\Payments\Domain\Idempotency\StoredResponse;
use App\Payments\Domain\Transaction;
use Illuminate\Http\JsonResponse;

/**
 * Builds the response the use case stores, so a replay returns exactly the same body.
 */
final readonly class TransactionResourcePresenter implements PaymentResponsePresenter
{
    public function present(Transaction $transaction): StoredResponse
    {
        return new StoredResponse(JsonResponse::HTTP_CREATED, (new TransactionResource($transaction))->resolve());
    }
}
