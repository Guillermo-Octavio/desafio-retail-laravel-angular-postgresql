<?php

declare(strict_types=1);

namespace App\Payments\Http\Resources;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Shows an instant in the business timezone, with its offset. The instant itself is stored and
 * logged in UTC; only its representation changes (DD-06).
 */
final class BusinessTime
{
    public static function format(DateTimeImmutable $instant): string
    {
        $timezone = new DateTimeZone((string) config('payments.business_timezone'));

        return $instant->setTimezone($timezone)->format(DateTimeInterface::RFC3339_EXTENDED);
    }
}
