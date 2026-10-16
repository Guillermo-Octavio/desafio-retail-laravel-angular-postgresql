<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Payments\Application\Ports\MonotonicTimer;

/**
 * Moves forward a fixed number of milliseconds on every reading.
 */
final class SteppingTimer implements MonotonicTimer
{
    private const int NANOSECONDS_PER_MILLISECOND = 1_000_000;

    private int $nanoseconds = 0;

    public function __construct(private readonly int $stepMilliseconds) {}

    public function nanoseconds(): int
    {
        $current = $this->nanoseconds;
        $this->nanoseconds += $this->stepMilliseconds * self::NANOSECONDS_PER_MILLISECOND;

        return $current;
    }
}
