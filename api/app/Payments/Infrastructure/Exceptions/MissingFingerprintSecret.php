<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Exceptions;

use LogicException;

final class MissingFingerprintSecret extends LogicException
{
    public static function create(): self
    {
        return new self('The request fingerprint needs the application key; set APP_KEY.');
    }
}
