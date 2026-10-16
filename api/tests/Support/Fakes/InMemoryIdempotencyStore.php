<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Payments\Domain\Exceptions\IdempotencyKeyNotReserved;
use App\Payments\Domain\Idempotency\IdempotencyKey;
use App\Payments\Domain\Idempotency\IdempotencyReservation;
use App\Payments\Domain\Idempotency\RequestFingerprint;
use App\Payments\Domain\Idempotency\StoredResponse;
use App\Payments\Domain\MerchantId;
use App\Payments\Domain\Ports\IdempotencyStore;
use App\Payments\Domain\TransactionId;

final class InMemoryIdempotencyStore implements IdempotencyStore
{
    /** @var array<string, array{fingerprint: RequestFingerprint, transactionId: ?TransactionId, response: ?StoredResponse}> */
    public array $keys = [];

    public function reserve(MerchantId $merchantId, IdempotencyKey $key, RequestFingerprint $fingerprint): IdempotencyReservation
    {
        $id = $this->id($merchantId, $key);

        if (! isset($this->keys[$id])) {
            $this->keys[$id] = ['fingerprint' => $fingerprint, 'transactionId' => null, 'response' => null];

            return IdempotencyReservation::reserved();
        }

        return IdempotencyReservation::forExistingKey($this->keys[$id]['fingerprint'], $fingerprint, $this->keys[$id]['response']);
    }

    public function attachTransaction(MerchantId $merchantId, IdempotencyKey $key, TransactionId $transactionId): void
    {
        $this->keys[$this->reservedId($merchantId, $key)]['transactionId'] = $transactionId;
    }

    public function complete(MerchantId $merchantId, IdempotencyKey $key, StoredResponse $response): void
    {
        $this->keys[$this->reservedId($merchantId, $key)]['response'] = $response;
    }

    private function reservedId(MerchantId $merchantId, IdempotencyKey $key): string
    {
        $id = $this->id($merchantId, $key);

        return isset($this->keys[$id]) ? $id : throw IdempotencyKeyNotReserved::forMerchantKey();
    }

    private function id(MerchantId $merchantId, IdempotencyKey $key): string
    {
        return $merchantId->value."\0".$key->value;
    }
}
