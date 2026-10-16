<?php

declare(strict_types=1);

namespace App\Payments\Domain\Ports;

use App\Payments\Domain\Transaction;

interface TransactionRepository
{
    /**
     * Persists the current state together with the events recorded since the last save,
     * in a single database transaction.
     *
     * The events are taken from the aggregate before writing, so after a failed save the
     * instance must be discarded instead of saved again.
     */
    public function save(Transaction $transaction): void;
}
