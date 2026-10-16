<?php

declare(strict_types=1);

namespace App\Payments\Application\Queries;

final readonly class TransactionPage
{
    /**
     * @param  list<TransactionSummary>  $items  newest first
     */
    public function __construct(
        public array $items,
        public int $page,
        public int $perPage,
        public int $total,
    ) {}

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }
}
