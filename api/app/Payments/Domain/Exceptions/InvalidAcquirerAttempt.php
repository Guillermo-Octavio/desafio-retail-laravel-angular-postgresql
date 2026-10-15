<?php

declare(strict_types=1);

namespace App\Payments\Domain\Exceptions;

final class InvalidAcquirerAttempt extends PaymentsDomainException
{
    public static function nonPositiveAttempt(int $attempt): self
    {
        return new self("The attempt number must be positive, {$attempt} given.");
    }

    public static function negativeDuration(int $durationMs): self
    {
        return new self("The attempt duration cannot be negative, {$durationMs} ms given.");
    }
}
