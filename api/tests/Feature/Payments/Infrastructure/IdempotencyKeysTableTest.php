<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\PaymentRows;

it('accepts a key only once per merchant', function () {
    DB::table('idempotency_keys')->insert(PaymentRows::idempotencyKey());

    expect(fn () => DB::table('idempotency_keys')->insert(PaymentRows::idempotencyKey()))
        ->toThrow(QueryException::class, 'violates unique constraint');
});

it('lets two merchants use the same key', function () {
    DB::table('idempotency_keys')->insert(PaymentRows::idempotencyKey());
    DB::table('idempotency_keys')->insert(PaymentRows::idempotencyKey(['merchant_id' => 'merchant-002']));

    expect(DB::table('idempotency_keys')->count())->toBe(2);
});

it('links a completed key to its transaction and stores the original response', function () {
    DB::table('transactions')->insert(PaymentRows::transaction());
    DB::table('idempotency_keys')->insert(PaymentRows::idempotencyKey([
        'status' => 'COMPLETED',
        'transaction_id' => PaymentRows::TRANSACTION_ID,
        'response_status' => 201,
        'response_body' => json_encode(['status' => 'PENDING']),
    ]));

    $key = DB::table('idempotency_keys')->selectRaw("transaction_id, response_status, response_body->>'status' as status")->first();

    expect($key->transaction_id)->toBe(PaymentRows::TRANSACTION_ID)
        ->and($key->response_status)->toBe(201)
        ->and($key->status)->toBe('PENDING');
});

it('rejects a key linked to a transaction that does not exist', function () {
    expect(fn () => DB::table('idempotency_keys')->insert(PaymentRows::idempotencyKey([
        'transaction_id' => PaymentRows::TRANSACTION_ID,
    ])))->toThrow(QueryException::class, 'violates foreign key constraint');
});
