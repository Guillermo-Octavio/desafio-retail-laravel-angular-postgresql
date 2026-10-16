<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/**
 * Reads what the payment adapters persisted, straight from the tables.
 */
final class StoredPayments
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function events(): array
    {
        return DB::table('transaction_events')
            ->orderBy('id')
            ->selectRaw("type, from_status, to_status, reason, metadata, correlation_id, to_char(occurred_at AT TIME ZONE 'UTC', 'HH24:MI:SS.US') as occurred_at")
            ->get()
            ->map(fn (object $row) => [...(array) $row, 'metadata' => $row->metadata === null ? null : json_decode($row->metadata, true)])
            ->all();
    }
}
