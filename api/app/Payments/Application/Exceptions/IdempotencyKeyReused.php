<?php

declare(strict_types=1);

namespace App\Payments\Application\Exceptions;

use RuntimeException;

final class IdempotencyKeyReused extends RuntimeException
{
    public static function withDifferentContent(): self
    {
        return new self('The idempotency key was already used with a different request.');
    }
}
