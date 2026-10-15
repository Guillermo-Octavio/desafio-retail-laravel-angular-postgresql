<?php

declare(strict_types=1);

namespace App\Payments\Domain;

use DateTimeImmutable;

/**
 * Immutable record of something that happened to a transaction. Only the aggregate
 * creates events (DD-01).
 */
final readonly class TransactionEvent
{
    /**
     * @param  array<string, int|string>  $metadata
     */
    private function __construct(
        public EventType $type,
        public ?TransactionStatus $fromStatus,
        public ?TransactionStatus $toStatus,
        public ?DeclineReason $reason,
        public array $metadata,
        public CorrelationId $correlationId,
        public DateTimeImmutable $occurredAt,
    ) {}

    public static function statusChanged(
        ?TransactionStatus $from,
        TransactionStatus $to,
        ?DeclineReason $reason,
        CorrelationId $correlationId,
        DateTimeImmutable $occurredAt,
    ): self {
        return new self(EventType::STATUS_CHANGED, $from, $to, $reason, [], $correlationId, $occurredAt);
    }

    public static function acquirerAttempt(
        int $attempt,
        AcquirerOutcome $outcome,
        int $durationMs,
        ?string $errorCode,
        CorrelationId $correlationId,
        DateTimeImmutable $occurredAt,
    ): self {
        $metadata = ['attempt' => $attempt, 'outcome' => $outcome->value, 'duration_ms' => $durationMs];

        if ($errorCode !== null) {
            $metadata['error_code'] = $errorCode;
        }

        return new self(EventType::ACQUIRER_ATTEMPT, null, null, null, $metadata, $correlationId, $occurredAt);
    }
}
