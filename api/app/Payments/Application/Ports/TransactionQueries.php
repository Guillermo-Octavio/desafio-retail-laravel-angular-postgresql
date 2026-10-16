<?php

declare(strict_types=1);

namespace App\Payments\Application\Ports;

use App\Payments\Application\Queries\TransactionDetails;
use App\Payments\Application\Queries\TransactionFilter;
use App\Payments\Application\Queries\TransactionPage;
use App\Payments\Domain\TransactionId;

/**
 * Read side: queries return read models and never change state (architecture section 4.2).
 */
interface TransactionQueries
{
    public function findDetails(TransactionId $transactionId): ?TransactionDetails;

    /**
     * Matching transactions, newest first.
     */
    public function search(TransactionFilter $filter, int $page, int $perPage): TransactionPage;
}
