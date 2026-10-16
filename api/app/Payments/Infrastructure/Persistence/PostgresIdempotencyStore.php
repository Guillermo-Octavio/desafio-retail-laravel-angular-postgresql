<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Persistence;

use App\Payments\Domain\Exceptions\IdempotencyKeyNotReserved;
use App\Payments\Domain\Idempotency\IdempotencyKey;
use App\Payments\Domain\Idempotency\IdempotencyReservation;
use App\Payments\Domain\Idempotency\RequestFingerprint;
use App\Payments\Domain\Idempotency\StoredResponse;
use App\Payments\Domain\MerchantId;
use App\Payments\Domain\Ports\Clock;
use App\Payments\Domain\Ports\IdempotencyStore;
use App\Payments\Domain\TransactionId;
use App\Payments\Infrastructure\Persistence\Models\IdempotencyKeyRecord;
use App\Payments\Infrastructure\Persistence\Models\IdempotencyKeyStatus;
use Illuminate\Database\Eloquent\Builder;

final readonly class PostgresIdempotencyStore implements IdempotencyStore
{
    private const array UNIQUE_KEY = ['merchant_id', 'idempotency_key'];

    public function __construct(private Clock $clock) {}

    /**
     * INSERT ... ON CONFLICT (merchant_id, idempotency_key) DO NOTHING: of two concurrent
     * requests only one inserts the row, and the other reads the existing one (DA-06).
     */
    public function reserve(MerchantId $merchantId, IdempotencyKey $key, RequestFingerprint $fingerprint): IdempotencyReservation
    {
        $now = $this->now();
        $inserted = IdempotencyKeyRecord::query()->toBase()->insertOrIgnoreReturning([
            'merchant_id' => $merchantId->value,
            'idempotency_key' => $key->value,
            'request_fingerprint' => $fingerprint->value,
            'status' => IdempotencyKeyStatus::IN_PROGRESS->value,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['id'], self::UNIQUE_KEY);

        if ($inserted->isNotEmpty()) {
            return IdempotencyReservation::reserved();
        }

        $existing = $this->record($merchantId, $key)->first()
            ?? throw IdempotencyKeyNotReserved::conflictNotVisible();

        return IdempotencyReservation::forExistingKey(
            new RequestFingerprint($existing->request_fingerprint),
            $fingerprint,
            $existing->status === IdempotencyKeyStatus::COMPLETED
                ? new StoredResponse($existing->response_status, $existing->response_body)
                : null,
        );
    }

    public function attachTransaction(MerchantId $merchantId, IdempotencyKey $key, TransactionId $transactionId): void
    {
        $this->updateReservedKey($merchantId, $key, [
            'transaction_id' => $transactionId->value,
            'updated_at' => $this->now(),
        ]);
    }

    public function complete(MerchantId $merchantId, IdempotencyKey $key, StoredResponse $response): void
    {
        $this->updateReservedKey($merchantId, $key, [
            'status' => IdempotencyKeyStatus::COMPLETED->value,
            'response_status' => $response->statusCode,
            'response_body' => json_encode($response->body, JSON_THROW_ON_ERROR),
            'updated_at' => $this->now(),
        ]);
    }

    /**
     * @param  array<string, int|string>  $changes
     */
    private function updateReservedKey(MerchantId $merchantId, IdempotencyKey $key, array $changes): void
    {
        if ($this->record($merchantId, $key)->update($changes) === 0) {
            throw IdempotencyKeyNotReserved::forMerchantKey();
        }
    }

    /**
     * @return Builder<IdempotencyKeyRecord>
     */
    private function record(MerchantId $merchantId, IdempotencyKey $key): Builder
    {
        return IdempotencyKeyRecord::query()
            ->where('merchant_id', $merchantId->value)
            ->where('idempotency_key', $key->value);
    }

    private function now(): string
    {
        return PostgresTimestamp::format($this->clock->now());
    }
}
