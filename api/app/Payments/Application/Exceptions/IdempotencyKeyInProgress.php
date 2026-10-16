<?php

declare(strict_types=1);

namespace App\Payments\Application\Exceptions;

use RuntimeException;

final class IdempotencyKeyInProgress extends RuntimeException
{
    public static function create(): self
    {
        return new self('A request with this idempotency key is still being processed.');
    }
}
