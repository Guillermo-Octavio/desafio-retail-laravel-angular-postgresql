<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Payments\Domain\Ports\TransactionRepository;
use App\Payments\Domain\Transaction;
use App\Payments\Domain\TransactionEvent;
use App\Payments\Domain\TransactionStatus;

final class InMemoryTransactionRepository implements TransactionRepository
{
    /** @var array<string, Transaction> */
    private array $transactions = [];

    /** @var list<TransactionStatus> */
    public array $savedStatuses = [];

    /** @var list<TransactionEvent> */
    public array $events = [];

    public function save(Transaction $transaction): void
    {
        $this->transactions[$transaction->id()->value] = $transaction;
        $this->savedStatuses[] = $transaction->status();
        array_push($this->events, ...$transaction->recordedEvents());
        $transaction->clearRecordedEvents();
    }

    public function count(): int
    {
        return count($this->transactions);
    }

    public function lastSavedStatus(): ?TransactionStatus
    {
        return $this->savedStatuses === [] ? null : $this->savedStatuses[array_key_last($this->savedStatuses)];
    }
}
