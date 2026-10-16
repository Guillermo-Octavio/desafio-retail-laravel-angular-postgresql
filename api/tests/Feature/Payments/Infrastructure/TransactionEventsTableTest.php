<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\PaymentRows;

beforeEach(function () {
    DB::table('transactions')->insert(PaymentRows::transaction());
});

it('stores events with microsecond precision and JSON metadata', function () {
    DB::table('transaction_events')->insert(PaymentRows::event([
        'type' => 'ACQUIRER_ATTEMPT',
        'to_status' => null,
        'metadata' => json_encode(['attempt' => 1, 'outcome' => 'TEMPORARY_ERROR', 'duration_ms' => 2001]),
    ]));

    $event = DB::table('transaction_events')
        ->selectRaw("to_char(occurred_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS.US') as occurred_at, metadata->>'outcome' as outcome")
        ->first();

    expect($event->occurred_at)->toBe('2026-10-15 12:00:00.123456')
        ->and($event->outcome)->toBe('TEMPORARY_ERROR');
});

it('rejects an event for a transaction that does not exist', function () {
    expect(fn () => DB::table('transaction_events')->insert(PaymentRows::event([
        'transaction_id' => '0199f0c2-6a1e-7c3a-9b2f-5d1e8a4c7b11',
    ])))->toThrow(QueryException::class, 'violates foreign key constraint');
});

it('never lets an event be modified', function () {
    DB::table('transaction_events')->insert(PaymentRows::event());

    expect(fn () => DB::table('transaction_events')->update(['to_status' => 'APPROVED']))
        ->toThrow(QueryException::class, 'transaction_events is append-only');
});

it('never lets an event be deleted', function () {
    DB::table('transaction_events')->insert(PaymentRows::event());

    expect(fn () => DB::table('transaction_events')->delete())
        ->toThrow(QueryException::class, 'transaction_events is append-only');
});

it('indexes the history of each transaction in insertion order', function () {
    $indexes = DB::table('pg_indexes')
        ->where('tablename', 'transaction_events')
        ->pluck('indexdef')
        ->map(fn (string $definition) => preg_replace('/^.* USING btree /', '', $definition))
        ->all();

    expect($indexes)->toContain('(transaction_id, id)');
});
