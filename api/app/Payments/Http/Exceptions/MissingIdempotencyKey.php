<?php

declare(strict_types=1);

namespace App\Payments\Http\Exceptions;

use RuntimeException;

final class MissingIdempotencyKey extends RuntimeException
{
    public static function create(): self
    {
        return new self('The Idempotency-Key header is required.');
    }
}
