<?php

declare(strict_types=1);

namespace App\Payments\Application\Exceptions;

use RuntimeException;

final class TransactionNotFound extends RuntimeException
{
    public static function create(): self
    {
        return new self('The transaction was not found.');
    }
}
