<?php

declare(strict_types=1);

namespace App\Payments\Domain\Ports;

use App\Payments\Domain\Exceptions\IdempotencyKeyNotReserved;
use App\Payments\Domain\Idempotency\IdempotencyKey;
use App\Payments\Domain\Idempotency\IdempotencyReservation;
use App\Payments\Domain\Idempotency\RequestFingerprint;
use App\Payments\Domain\Idempotency\StoredResponse;
use App\Payments\Domain\MerchantId;
use App\Payments\Domain\TransactionId;

interface IdempotencyStore
{
    /**
     * Reserves the key atomically through the database unique constraint (DD-09). When
     * the key already exists, the outcome comes from IdempotencyReservation::forExistingKey().
     *
     * @throws IdempotencyKeyNotReserved when the existing key cannot be read back.
     */
    public function reserve(MerchantId $merchantId, IdempotencyKey $key, RequestFingerprint $fingerprint): IdempotencyReservation;

    /**
     * @throws IdempotencyKeyNotReserved when the merchant has not reserved the key.
     */
    public function attachTransaction(MerchantId $merchantId, IdempotencyKey $key, TransactionId $transactionId): void;

    /**
     * @throws IdempotencyKeyNotReserved when the merchant has not reserved the key.
     */
    public function complete(MerchantId $merchantId, IdempotencyKey $key, StoredResponse $response): void;
}
