<?php

declare(strict_types=1);

namespace App\Payments\Application\Queries;

use App\Payments\Domain\TransactionStatus;

final readonly class TransactionFilter
{
    public function __construct(
        public ?string $merchantId = null,
        public ?TransactionStatus $status = null,
    ) {}
}
