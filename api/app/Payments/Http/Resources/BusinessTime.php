<?php

declare(strict_types=1);

namespace App\Payments\Http\Resources;

use App\Payments\Application\BusinessTimezone;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Shows an instant in the business timezone, with its offset. The instant itself is stored and
 * logged in UTC; only its representation changes (DD-06).
 */
final class BusinessTime
{
    public static function format(DateTimeImmutable $instant): string
    {
        return $instant->setTimezone(app(BusinessTimezone::class)->zone)->format(DateTimeInterface::RFC3339_EXTENDED);
    }
}
