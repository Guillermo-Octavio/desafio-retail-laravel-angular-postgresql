<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Payments\Application\Ports\PaymentResponsePresenter;
use App\Payments\Domain\Idempotency\StoredResponse;
use App\Payments\Domain\Transaction;

final class ArrayResponsePresenter implements PaymentResponsePresenter
{
    public function present(Transaction $transaction): StoredResponse
    {
        return new StoredResponse(201, [
            'transaction_id' => $transaction->id()->value,
            'status' => $transaction->status()->value,
            'decline_reason' => $transaction->declineReason()?->value,
        ]);
    }
}
