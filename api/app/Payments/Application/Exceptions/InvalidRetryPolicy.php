<?php

declare(strict_types=1);

namespace App\Payments\Application\Exceptions;

use LogicException;

final class InvalidRetryPolicy extends LogicException
{
    public static function nonPositiveAttempts(int $maxAttempts): self
    {
        return new self("The acquirer retry policy needs at least 1 attempt, {$maxAttempts} given.");
    }

    public static function negativeDelay(string $setting, int $value): self
    {
        return new self("The acquirer retry {$setting} cannot be negative, {$value} given.");
    }
}
