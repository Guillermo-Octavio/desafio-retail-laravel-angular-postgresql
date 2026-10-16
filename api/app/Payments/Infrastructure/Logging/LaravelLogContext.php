<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Logging;

use App\Payments\Application\Ports\LogContext;
use App\Payments\Domain\TransactionId;
use Illuminate\Support\Facades\Context;

final readonly class LaravelLogContext implements LogContext
{
    public const string TRANSACTION_ID = 'transaction_id';

    public function addTransactionId(TransactionId $transactionId): void
    {
        Context::add(self::TRANSACTION_ID, $transactionId->value);
    }
}
