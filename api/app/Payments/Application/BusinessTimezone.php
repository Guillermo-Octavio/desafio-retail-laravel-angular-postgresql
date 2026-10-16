<?php

declare(strict_types=1);

namespace App\Payments\Application;

use DateTimeZone;

/**
 * The timezone of the business: card expiry is evaluated in it and dates are shown in it.
 * One validated instance is shared, built once from the configuration.
 */
final readonly class BusinessTimezone
{
    public function __construct(public DateTimeZone $zone) {}
}
