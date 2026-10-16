<?php

declare(strict_types=1);

use App\Payments\Application\ListTransactions;
use App\Payments\Application\Ports\TransactionQueries;
use App\Payments\Application\Queries\TransactionDetails;
use App\Payments\Application\Queries\TransactionFilter;
use App\Payments\Application\Queries\TransactionPage;
use App\Payments\Domain\TransactionId;

it('uses 20 per page by default and never more than 100', function (?int $requested, int $used) {
    $queries = new class implements TransactionQueries
    {
        public ?int $perPage = null;

        public function findDetails(TransactionId $transactionId): ?TransactionDetails
        {
            return null;
        }

        public function search(TransactionFilter $filter, int $page, int $perPage): TransactionPage
        {
            $this->perPage = $perPage;

            return new TransactionPage([], $page, $perPage, 0);
        }
    };

    (new ListTransactions($queries))->execute(new TransactionFilter, 1, $requested);

    expect($queries->perPage)->toBe($used);
})->with([
    'not given' => [null, 20],
    'within range' => [50, 50],
    'at the maximum' => [100, 100],
    'above the maximum' => [500, 100],
]);

it('counts at least one page even when there are no results', function () {
    expect((new TransactionPage([], 1, 20, 0))->lastPage())->toBe(1)
        ->and((new TransactionPage([], 1, 20, 41))->lastPage())->toBe(3);
});
