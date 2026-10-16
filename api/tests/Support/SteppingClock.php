<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Payments\Domain\Ports\Clock;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Moves forward a fixed number of milliseconds on every reading, to measure durations.
 */
final class SteppingClock implements Clock
{
    private DateTimeImmutable $now;

    public function __construct(string $start, private readonly int $stepMilliseconds)
    {
        $this->now = new DateTimeImmutable($start, new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        $current = $this->now;
        $this->now = $this->now->modify("+{$this->stepMilliseconds} milliseconds");

        return $current;
    }
}
