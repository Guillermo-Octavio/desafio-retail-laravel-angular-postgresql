<?php

declare(strict_types=1);

namespace App\Payments\Domain\Ports;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
