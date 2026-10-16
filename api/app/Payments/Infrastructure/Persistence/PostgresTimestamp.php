<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Persistence;

use DateTimeInterface;

/**
 * Formats a moment for a timestamptz(6) column, keeping microseconds and the offset.
 */
final class PostgresTimestamp
{
    private const string FORMAT = 'Y-m-d H:i:s.uP';

    public static function format(DateTimeInterface $moment): string
    {
        return $moment->format(self::FORMAT);
    }
}
