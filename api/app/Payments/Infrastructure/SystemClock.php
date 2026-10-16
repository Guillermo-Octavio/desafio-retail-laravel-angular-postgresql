<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure;

use App\Payments\Domain\Ports\Clock;
use DateTimeImmutable;
use Illuminate\Support\Carbon;

final readonly class SystemClock implements Clock
{
    private const string TIMEZONE = 'UTC';

    // Carbon honors Carbon::setTestNow(), so feature tests can freeze the time.
    public function now(): DateTimeImmutable
    {
        return Carbon::now(self::TIMEZONE)->toDateTimeImmutable();
    }
}
