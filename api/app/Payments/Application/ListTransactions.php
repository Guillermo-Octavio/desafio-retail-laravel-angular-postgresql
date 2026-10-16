<?php

declare(strict_types=1);

namespace App\Payments\Application;

use App\Payments\Application\Ports\TransactionQueries;
use App\Payments\Application\Queries\TransactionFilter;
use App\Payments\Application\Queries\TransactionPage;

final readonly class ListTransactions
{
    public const int DEFAULT_PER_PAGE = 20;

    public const int MAX_PER_PAGE = 100;

    public function __construct(private TransactionQueries $queries) {}

    /**
     * A page size above the maximum is capped instead of rejected (SUP-13).
     */
    public function execute(TransactionFilter $filter, int $page, ?int $perPage): TransactionPage
    {
        return $this->queries->search($filter, $page, min($perPage ?? self::DEFAULT_PER_PAGE, self::MAX_PER_PAGE));
    }
}
