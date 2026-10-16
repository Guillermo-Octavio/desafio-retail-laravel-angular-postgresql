<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Exceptions;

use LogicException;

final class InvalidBusinessTimezone extends LogicException
{
    public static function named(string $timezone): self
    {
        return new self("PAYMENTS_BUSINESS_TIMEZONE must be a valid timezone identifier, '{$timezone}' given.");
    }
}
