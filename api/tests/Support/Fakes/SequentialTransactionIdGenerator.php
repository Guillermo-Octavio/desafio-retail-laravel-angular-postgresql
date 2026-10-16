<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Payments\Domain\Ports\TransactionIdGenerator;
use App\Payments\Domain\TransactionId;

final class SequentialTransactionIdGenerator implements TransactionIdGenerator
{
    private int $next = 1;

    public function generate(): TransactionId
    {
        return new TransactionId(sprintf('0199f0c2-6a1e-7c3a-9b2f-%012d', $this->next++));
    }
}
