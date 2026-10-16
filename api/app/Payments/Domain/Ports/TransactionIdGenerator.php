<?php

declare(strict_types=1);

namespace App\Payments\Domain\Ports;

use App\Payments\Domain\TransactionId;

interface TransactionIdGenerator
{
    public function generate(): TransactionId;
}
