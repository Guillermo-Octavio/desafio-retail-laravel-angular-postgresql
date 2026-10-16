<?php

declare(strict_types=1);

namespace App\Payments\Application\Queries;

use DateTimeImmutable;

final readonly class TransactionEventView
{
    /**
     * @param  array<string, int|string>|null  $metadata
     */
    public function __construct(
        public string $type,
        public ?string $fromStatus,
        public ?string $toStatus,
        public ?string $reason,
        public ?array $metadata,
        public string $correlationId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
