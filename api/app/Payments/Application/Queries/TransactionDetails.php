<?php

declare(strict_types=1);

namespace App\Payments\Application\Queries;

final readonly class TransactionDetails
{
    /**
     * @param  list<TransactionEventView>  $events  in the order they happened
     */
    public function __construct(
        public TransactionSummary $summary,
        public array $events,
    ) {}
}
