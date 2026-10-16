<?php

declare(strict_types=1);

namespace App\Payments\Domain\Ports;

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
     */
    public function reserve(MerchantId $merchantId, IdempotencyKey $key, RequestFingerprint $fingerprint): IdempotencyReservation;

    public function attachTransaction(MerchantId $merchantId, IdempotencyKey $key, TransactionId $transactionId): void;

    public function complete(MerchantId $merchantId, IdempotencyKey $key, StoredResponse $response): void;
}
