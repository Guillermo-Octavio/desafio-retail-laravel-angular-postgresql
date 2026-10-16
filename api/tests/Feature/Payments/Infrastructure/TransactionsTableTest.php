<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\PaymentRows;

it('stores a transaction that satisfies every constraint', function (array $overrides) {
    DB::table('transactions')->insert(PaymentRows::transaction($overrides));

    expect(DB::table('transactions')->count())->toBe(1);
})->with([
    'pending' => [[]],
    'processing' => [['status' => 'PROCESSING']],
    'approved with reference' => [['status' => 'APPROVED', 'acquirer_reference' => 'A1B2C3']],
    'declined with reason' => [['status' => 'DECLINED', 'decline_reason' => 'CARD_EXPIRED']],
    'failed with reason' => [['status' => 'FAILED', 'decline_reason' => 'ACQUIRER_UNAVAILABLE']],
    'USD' => [['currency' => 'USD', 'amount' => 1050]],
]);

it('rejects a transaction that breaks a constraint', function (array $overrides) {
    expect(fn () => DB::table('transactions')->insert(PaymentRows::transaction($overrides)))
        ->toThrow(QueryException::class, 'violates check constraint');
})->with([
    'unknown status' => [['status' => 'REFUNDED']],
    'unsupported currency' => [['currency' => 'EUR']],
    'zero amount' => [['amount' => 0]],
    'negative amount' => [['amount' => -1]],
    'declined without reason' => [['status' => 'DECLINED']],
    'failed without reason' => [['status' => 'FAILED']],
    'pending with reason' => [['decline_reason' => 'CARD_EXPIRED']],
    'approved with reason' => [['status' => 'APPROVED', 'acquirer_reference' => 'A1B2C3', 'decline_reason' => 'CARD_EXPIRED']],
    'approved without reference' => [['status' => 'APPROVED']],
    'processing with reference' => [['status' => 'PROCESSING', 'acquirer_reference' => 'A1B2C3']],
    'declined with reference' => [['status' => 'DECLINED', 'decline_reason' => 'INSUFFICIENT_FUNDS', 'acquirer_reference' => 'A1B2C3']],
]);

it('stores amounts beyond the 32-bit integer range', function () {
    DB::table('transactions')->insert(PaymentRows::transaction(['amount' => 9_000_000_000]));

    expect(DB::table('transactions')->value('amount'))->toBe(9_000_000_000);
});

it('indexes the listings by merchant, status and date, newest first', function () {
    $indexes = DB::table('pg_indexes')
        ->where('tablename', 'transactions')
        ->pluck('indexdef')
        ->map(fn (string $definition) => preg_replace('/^.* USING btree /', '', $definition))
        ->all();

    expect($indexes)->toContain('(id)')
        ->toContain('(merchant_id, created_at DESC)')
        ->toContain('(status, created_at DESC)')
        ->toContain('(created_at DESC)');
});
