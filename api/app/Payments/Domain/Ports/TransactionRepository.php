<?php

declare(strict_types=1);

namespace App\Payments\Domain\Ports;

use App\Payments\Domain\Transaction;

interface TransactionRepository
{
    /**
     * Persists the current state together with the recorded events in a single database
     * transaction, and clears the events only after the outermost commit, so a failed or rolled
     * back save can be retried.
     */
    public function save(Transaction $transaction): void;
}
