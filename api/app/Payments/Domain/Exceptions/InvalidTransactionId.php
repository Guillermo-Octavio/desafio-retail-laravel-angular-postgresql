<?php

declare(strict_types=1);

namespace App\Payments\Domain\Exceptions;

final class InvalidTransactionId extends PaymentsDomainException
{
    public static function notUuidV7(string $value): self
    {
        return new self("The transaction id must be a UUID v7, '{$value}' given.");
    }
}
