<?php

declare(strict_types=1);

namespace App\Payments\Domain\Exceptions;

use App\Payments\Domain\TransactionStatus;

/**
 * Signals a programming error: business outcomes never require a forbidden transition.
 */
final class InvalidTransactionTransition extends PaymentsDomainException
{
    public static function between(TransactionStatus $from, TransactionStatus $to): self
    {
        return new self("A transaction cannot move from {$from->value} to {$to->value}.");
    }

    public static function acquirerAttemptWhile(TransactionStatus $status): self
    {
        return new self("An acquirer attempt can only be recorded while PROCESSING, the transaction is {$status->value}.");
    }
}
