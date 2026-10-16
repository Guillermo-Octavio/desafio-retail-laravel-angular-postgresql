<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure;

use App\Payments\Application\Ports\MonotonicTimer;

final readonly class HrtimeMonotonicTimer implements MonotonicTimer
{
    public function nanoseconds(): int
    {
        return hrtime(true);
    }
}
