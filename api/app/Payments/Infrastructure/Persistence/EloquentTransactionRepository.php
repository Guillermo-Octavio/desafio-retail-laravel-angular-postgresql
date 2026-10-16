<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Persistence;

use App\Payments\Domain\Ports\Clock;
use App\Payments\Domain\Ports\TransactionRepository;
use App\Payments\Domain\Transaction;
use App\Payments\Domain\TransactionEvent;
use App\Payments\Infrastructure\Persistence\Models\TransactionEventRecord;
use App\Payments\Infrastructure\Persistence\Models\TransactionRecord;
use Illuminate\Support\Facades\DB;

final readonly class EloquentTransactionRepository implements TransactionRepository
{
    private const array MUTABLE_COLUMNS = ['status', 'decline_reason', 'acquirer_reference', 'updated_at'];

    public function __construct(private Clock $clock) {}

    public function save(Transaction $transaction): void
    {
        $events = $transaction->recordedEvents();

        DB::transaction(function () use ($transaction, $events): void {
            TransactionRecord::query()->upsert([$this->transactionRow($transaction)], ['id'], self::MUTABLE_COLUMNS);

            if ($events !== []) {
                TransactionEventRecord::query()->insert(array_map(
                    fn (TransactionEvent $event) => $this->eventRow($transaction, $event),
                    $events,
                ));
            }
        });

        $transaction->clearRecordedEvents();
    }

    /**
     * @return array<string, int|string|null>
     */
    private function transactionRow(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id()->value,
            'merchant_id' => $transaction->merchantId()->value,
            'amount' => $transaction->money()->amount,
            'currency' => $transaction->money()->currency->value,
            'status' => $transaction->status()->value,
            'decline_reason' => $transaction->declineReason()?->value,
            'acquirer_reference' => $transaction->acquirerReference(),
            'card_brand' => $transaction->card()->brand->value,
            'card_last4' => $transaction->card()->last4,
            'correlation_id' => $transaction->correlationId()->value,
            'created_at' => PostgresTimestamp::format($transaction->createdAt()),
            'updated_at' => PostgresTimestamp::format($this->clock->now()),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function eventRow(Transaction $transaction, TransactionEvent $event): array
    {
        return [
            'transaction_id' => $transaction->id()->value,
            'type' => $event->type->value,
            'from_status' => $event->fromStatus?->value,
            'to_status' => $event->toStatus?->value,
            'reason' => $event->reason?->value,
            'metadata' => $event->metadata === [] ? null : json_encode($event->metadata, JSON_THROW_ON_ERROR),
            'correlation_id' => $event->correlationId->value,
            'occurred_at' => PostgresTimestamp::format($event->occurredAt),
        ];
    }
}
