<?php

declare(strict_types=1);

namespace App\Payments\Application;

use App\Payments\Application\Exceptions\TransactionNotFound;
use App\Payments\Application\Ports\TransactionQueries;
use App\Payments\Application\Queries\TransactionDetails;
use App\Payments\Domain\TransactionId;

final readonly class GetTransaction
{
    public function __construct(private TransactionQueries $queries) {}

    /**
     * A malformed id is reported as not found, so no internal detail leaks (HU-07).
     *
     * @throws TransactionNotFound
     */
    public function execute(string $transactionId): TransactionDetails
    {
        $id = TransactionId::tryFrom($transactionId);

        if ($id === null) {
            throw TransactionNotFound::create();
        }

        return $this->queries->findDetails($id) ?? throw TransactionNotFound::create();
    }
}
