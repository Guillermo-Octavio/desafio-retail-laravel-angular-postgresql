<?php

declare(strict_types=1);

use App\Payments\Domain\Idempotency\IdempotencyKey;
use App\Payments\Domain\Idempotency\ReservationOutcome;
use App\Payments\Domain\Idempotency\StoredResponse;
use App\Payments\Domain\MerchantId;
use App\Payments\Domain\TransactionId;
use App\Payments\Infrastructure\Persistence\PostgresIdempotencyStore;
use Illuminate\Support\Facades\DB;
use Tests\Support\FixedClock;
use Tests\Support\PaymentFixtures;
use Tests\Support\PaymentRows;

beforeEach(function () {
    $this->store = new PostgresIdempotencyStore(new FixedClock('2026-10-15 12:00:00'));
    $this->merchant = new MerchantId('merchant-001');
    $this->key = new IdempotencyKey('key-1');
});

it('reserves a new key as in progress', function () {
    $reservation = $this->store->reserve($this->merchant, $this->key, PaymentFixtures::fingerprint('a'));

    expect($reservation->outcome)->toBe(ReservationOutcome::RESERVED)
        ->and(DB::table('idempotency_keys')->first(['merchant_id', 'idempotency_key', 'request_fingerprint', 'status', 'transaction_id']))
        ->toEqual((object) [
            'merchant_id' => 'merchant-001',
            'idempotency_key' => 'key-1',
            'request_fingerprint' => str_repeat('a', 64),
            'status' => 'IN_PROGRESS',
            'transaction_id' => null,
        ]);
});

it('reports a key that is still in progress without inserting it again', function () {
    $this->store->reserve($this->merchant, $this->key, PaymentFixtures::fingerprint('a'));

    $reservation = $this->store->reserve($this->merchant, $this->key, PaymentFixtures::fingerprint('a'));

    expect($reservation->outcome)->toBe(ReservationOutcome::IN_PROGRESS)
        ->and(DB::table('idempotency_keys')->count())->toBe(1);
});

it('reports a conflict when the same key comes with different content', function () {
    $this->store->reserve($this->merchant, $this->key, PaymentFixtures::fingerprint('a'));

    $reservation = $this->store->reserve($this->merchant, $this->key, PaymentFixtures::fingerprint('b'));

    expect($reservation->outcome)->toBe(ReservationOutcome::FINGERPRINT_MISMATCH);
});

it('replays the stored response of a completed key', function () {
    DB::table('transactions')->insert(PaymentRows::transaction());
    $this->store->reserve($this->merchant, $this->key, PaymentFixtures::fingerprint('a'));
    $this->store->attachTransaction($this->merchant, $this->key, new TransactionId(PaymentRows::TRANSACTION_ID));
    $this->store->complete($this->merchant, $this->key, new StoredResponse(201, ['id' => PaymentRows::TRANSACTION_ID, 'status' => 'APPROVED']));

    $reservation = $this->store->reserve($this->merchant, $this->key, PaymentFixtures::fingerprint('a'));

    expect($reservation->outcome)->toBe(ReservationOutcome::REPLAY)
        ->and($reservation->storedResponse->statusCode)->toBe(201)
        ->and($reservation->storedResponse->body)->toEqual(['id' => PaymentRows::TRANSACTION_ID, 'status' => 'APPROVED'])
        ->and(DB::table('idempotency_keys')->first(['status', 'transaction_id']))
        ->toEqual((object) ['status' => 'COMPLETED', 'transaction_id' => PaymentRows::TRANSACTION_ID]);
});

it('scopes keys to the merchant', function () {
    $this->store->reserve($this->merchant, $this->key, PaymentFixtures::fingerprint('a'));

    $reservation = $this->store->reserve(new MerchantId('merchant-002'), $this->key, PaymentFixtures::fingerprint('b'));

    expect($reservation->outcome)->toBe(ReservationOutcome::RESERVED);
});

it('detects a key that another connection reserved and committed first', function () {
    config(['database.connections.pgsql_concurrent' => config('database.connections.pgsql')]);
    $otherRequest = DB::connection('pgsql_concurrent');
    $otherRequest->table('idempotency_keys')->insert(PaymentRows::idempotencyKey());

    try {
        $reservation = $this->store->reserve($this->merchant, $this->key, PaymentFixtures::fingerprint('a'));

        expect($reservation->outcome)->toBe(ReservationOutcome::IN_PROGRESS)
            ->and(DB::table('idempotency_keys')->count())->toBe(1);
    } finally {
        // The other connection committed outside the test transaction, so it cleans up its own row.
        $otherRequest->table('idempotency_keys')->delete();
        $otherRequest->disconnect();
    }
});
