<?php

declare(strict_types=1);

namespace App\Payments\Application\Ports;

use App\Payments\Domain\TransactionId;

/**
 * Adds data to every later log line of the request, so it does not have to be passed by hand.
 */
interface LogContext
{
    public function addTransactionId(TransactionId $transactionId): void;
}
