<?php

declare(strict_types=1);

namespace App\Payments\Application\Ports;

/**
 * Measures elapsed time. Unlike the Clock port it never goes backwards, so a wall clock
 * adjustment cannot produce a negative duration.
 */
interface MonotonicTimer
{
    public function nanoseconds(): int;
}
