<?php

declare(strict_types=1);

namespace App\Payments\Domain\Ports;

use App\Payments\Domain\Transaction;

interface TransactionRepository
{
    /**
     * Persists the current state together with the events recorded since the last save,
     * in a single database transaction.
     */
    public function save(Transaction $transaction): void;
}
