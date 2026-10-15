<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Payments\Domain\Ports\Clock;
use DateTimeImmutable;
use DateTimeZone;

final readonly class FixedClock implements Clock
{
    private DateTimeImmutable $now;

    public function __construct(string $now)
    {
        $this->now = new DateTimeImmutable($now, new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
