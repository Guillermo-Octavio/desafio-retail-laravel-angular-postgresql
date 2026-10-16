<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure;

use App\Payments\Domain\Ports\Clock;
use App\Payments\Domain\Ports\TransactionIdGenerator;
use App\Payments\Domain\TransactionId;
use Illuminate\Support\Str;

final readonly class UuidV7TransactionIdGenerator implements TransactionIdGenerator
{
    public function __construct(private Clock $clock) {}

    public function generate(): TransactionId
    {
        return new TransactionId(Str::uuid7($this->clock->now())->toString());
    }
}
