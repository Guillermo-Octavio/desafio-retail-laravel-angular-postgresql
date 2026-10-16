<?php

declare(strict_types=1);

namespace App\Payments\Http\Controllers;

use App\Payments\Application\ListTransactions;
use App\Payments\Http\Requests\ListPaymentsRequest;
use App\Payments\Http\Resources\TransactionPageResource;

final readonly class ListPaymentsController
{
    public function __construct(private ListTransactions $listTransactions) {}

    public function __invoke(ListPaymentsRequest $request): TransactionPageResource
    {
        return new TransactionPageResource($this->listTransactions->execute($request->filter(), $request->page(), $request->perPage()));
    }
}
