<?php

declare(strict_types=1);

namespace App\Payments\Application\Ports;

interface Sleeper
{
    public function sleepMilliseconds(int $milliseconds): void;
}
