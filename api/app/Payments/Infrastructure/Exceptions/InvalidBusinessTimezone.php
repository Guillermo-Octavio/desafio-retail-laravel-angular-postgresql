<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Exceptions;

use LogicException;
use Throwable;

final class InvalidBusinessTimezone extends LogicException
{
    public static function named(string $timezone, ?Throwable $previous = null): self
    {
        return new self("PAYMENTS_BUSINESS_TIMEZONE must be a valid timezone, '{$timezone}' given.", 0, $previous);
    }
}
