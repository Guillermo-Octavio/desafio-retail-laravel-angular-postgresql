<?php

declare(strict_types=1);

namespace App\Payments\Application\Ports;

use App\Payments\Domain\Idempotency\StoredResponse;
use App\Payments\Domain\Transaction;

interface PaymentResponsePresenter
{
    /**
     * Builds the exact response returned to the merchant, so a replay can return it unchanged.
     */
    public function present(Transaction $transaction): StoredResponse;
}
