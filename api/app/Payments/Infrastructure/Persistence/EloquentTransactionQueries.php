<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Persistence;

use App\Payments\Application\Ports\TransactionQueries;
use App\Payments\Application\Queries\TransactionDetails;
use App\Payments\Application\Queries\TransactionEventView;
use App\Payments\Application\Queries\TransactionFilter;
use App\Payments\Application\Queries\TransactionPage;
use App\Payments\Application\Queries\TransactionSummary;
use App\Payments\Domain\TransactionId;
use App\Payments\Infrastructure\Persistence\Models\TransactionEventRecord;
use App\Payments\Infrastructure\Persistence\Models\TransactionRecord;
use DateTimeImmutable;
use DateTimeZone;

final readonly class EloquentTransactionQueries implements TransactionQueries
{
    private const string TIMEZONE = 'UTC';

    public function findDetails(TransactionId $transactionId): ?TransactionDetails
    {
        $record = TransactionRecord::query()->find($transactionId->value);

        if ($record === null) {
            return null;
        }

        $events = TransactionEventRecord::query()
            ->where('transaction_id', $transactionId->value)
            ->orderBy('sequence')
            ->get()
            ->map(fn (TransactionEventRecord $event) => $this->eventView($event))
            ->all();

        return new TransactionDetails($this->summary($record), $events);
    }

    public function search(TransactionFilter $filter, int $page, int $perPage): TransactionPage
    {
        $paginator = TransactionRecord::query()
            ->when($filter->merchantId !== null, fn ($query) => $query->where('merchant_id', $filter->merchantId))
            ->when($filter->status !== null, fn ($query) => $query->where('status', $filter->status?->value))
            // id breaks ties: UUID v7 grows with time, so the order stays stable.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(perPage: $perPage, page: $page);

        return new TransactionPage(
            array_values(array_map(fn (TransactionRecord $record) => $this->summary($record), $paginator->items())),
            $paginator->currentPage(),
            $paginator->perPage(),
            $paginator->total(),
        );
    }

    private function summary(TransactionRecord $record): TransactionSummary
    {
        return new TransactionSummary(
            $record->id,
            $record->merchant_id,
            (int) $record->amount,
            $record->currency,
            $record->status,
            $record->decline_reason,
            $record->card_brand,
            $record->card_last4,
            $this->utc($record->created_at),
            $this->utc($record->updated_at),
        );
    }

    private function eventView(TransactionEventRecord $event): TransactionEventView
    {
        return new TransactionEventView(
            $event->type,
            $event->from_status,
            $event->to_status,
            $event->reason,
            $event->metadata === null ? null : json_decode($event->metadata, true, flags: JSON_THROW_ON_ERROR),
            $event->correlation_id,
            $this->utc($event->occurred_at),
        );
    }

    private function utc(string $timestamp): DateTimeImmutable
    {
        return (new DateTimeImmutable($timestamp))->setTimezone(new DateTimeZone(self::TIMEZONE));
    }
}
