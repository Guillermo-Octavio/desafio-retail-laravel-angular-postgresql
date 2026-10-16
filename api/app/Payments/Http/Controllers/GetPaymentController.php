<?php

declare(strict_types=1);

namespace App\Payments\Http\Controllers;

use App\Payments\Application\GetTransaction;
use App\Payments\Http\Resources\TransactionDetailsResource;

final readonly class GetPaymentController
{
    public function __construct(private GetTransaction $getTransaction) {}

    public function __invoke(string $transactionId): TransactionDetailsResource
    {
        return new TransactionDetailsResource($this->getTransaction->execute($transactionId));
    }
}
