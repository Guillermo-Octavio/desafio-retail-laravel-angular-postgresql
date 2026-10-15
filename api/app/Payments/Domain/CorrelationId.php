<?php

declare(strict_types=1);

namespace App\Payments\Domain;

use App\Payments\Domain\Exceptions\InvalidCorrelationId;

final readonly class CorrelationId
{
    private const int MAX_LENGTH = 64;

    public function __construct(public string $value)
    {
        $length = mb_strlen($value);

        if ($length === 0 || $length > self::MAX_LENGTH) {
            throw InvalidCorrelationId::invalidLength(self::MAX_LENGTH);
        }
    }
}
