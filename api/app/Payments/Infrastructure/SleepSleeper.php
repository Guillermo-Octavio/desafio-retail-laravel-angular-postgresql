<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure;

use App\Payments\Application\Ports\Sleeper;
use Illuminate\Support\Sleep;

// Laravel's Sleep can be faked, so feature tests never wait for real.
final readonly class SleepSleeper implements Sleeper
{
    public function sleepMilliseconds(int $milliseconds): void
    {
        Sleep::for($milliseconds)->milliseconds();
    }
}
