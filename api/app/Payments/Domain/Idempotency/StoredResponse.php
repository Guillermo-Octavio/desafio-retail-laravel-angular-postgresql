<?php

declare(strict_types=1);

namespace App\Payments\Domain\Idempotency;

/**
 * The original, already masked, response returned again when a request is replayed.
 */
final readonly class StoredResponse
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        public int $statusCode,
        public array $body,
    ) {}
}
