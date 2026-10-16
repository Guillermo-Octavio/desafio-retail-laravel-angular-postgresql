<?php

declare(strict_types=1);

namespace App\Payments\Application;

use App\Payments\Domain\Idempotency\StoredResponse;

final readonly class PaymentResponse
{
    private function __construct(
        public StoredResponse $response,
        public bool $replayed,
    ) {}

    public static function original(StoredResponse $response): self
    {
        return new self($response, false);
    }

    public static function replayed(StoredResponse $response): self
    {
        return new self($response, true);
    }
}
