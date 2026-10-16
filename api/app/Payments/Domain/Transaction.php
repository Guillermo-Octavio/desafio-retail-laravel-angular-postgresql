<?php

declare(strict_types=1);

namespace App\Payments\Domain;

use App\Payments\Domain\Card\MaskedCard;
use App\Payments\Domain\Exceptions\InvalidAcquirerAttempt;
use App\Payments\Domain\Exceptions\InvalidAcquirerReference;
use App\Payments\Domain\Exceptions\InvalidTransactionTransition;
use DateTimeImmutable;

/**
 * Aggregate root: the only way to change a transaction's status and to create its events
 * (DD-01). Every operation validates before mutating, so a rejected call leaves it intact.
 */
final class Transaction
{
    private const int MAX_ACQUIRER_REFERENCE_LENGTH = 64;

    private TransactionStatus $status = TransactionStatus::PENDING;

    private ?DeclineReason $declineReason = null;

    private ?string $acquirerReference = null;

    /** @var list<TransactionEvent> */
    private array $recordedEvents = [];

    private int $lastEventSequence = 0;

    private function __construct(
        private readonly TransactionId $id,
        private readonly MerchantId $merchantId,
        private readonly Money $money,
        private readonly MaskedCard $card,
        private readonly CorrelationId $correlationId,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        TransactionId $id,
        MerchantId $merchantId,
        Money $money,
        MaskedCard $card,
        CorrelationId $correlationId,
        DateTimeImmutable $occurredAt,
    ): self {
        $transaction = new self($id, $merchantId, $money, $card, $correlationId, $occurredAt);
        $transaction->recordedEvents[] = TransactionEvent::statusChanged(
            ++$transaction->lastEventSequence,
            null,
            TransactionStatus::PENDING,
            null,
            $correlationId,
            $occurredAt,
        );

        return $transaction;
    }

    public function startProcessing(DateTimeImmutable $occurredAt): void
    {
        $this->transitionTo(TransactionStatus::PROCESSING, null, $occurredAt);
    }

    public function decline(DeclineReason $reason, DateTimeImmutable $occurredAt): void
    {
        $this->transitionTo(TransactionStatus::DECLINED, $reason, $occurredAt);
    }

    public function fail(DeclineReason $reason, DateTimeImmutable $occurredAt): void
    {
        $this->transitionTo(TransactionStatus::FAILED, $reason, $occurredAt);
    }

    public function approve(string $acquirerReference, DateTimeImmutable $occurredAt): void
    {
        $length = mb_strlen($acquirerReference);

        if ($length === 0 || $length > self::MAX_ACQUIRER_REFERENCE_LENGTH) {
            throw InvalidAcquirerReference::invalidLength(self::MAX_ACQUIRER_REFERENCE_LENGTH);
        }

        $this->transitionTo(TransactionStatus::APPROVED, null, $occurredAt);
        $this->acquirerReference = $acquirerReference;
    }

    public function recordAcquirerAttempt(
        int $attempt,
        AcquirerOutcome $outcome,
        int $durationMs,
        ?string $errorCode,
        DateTimeImmutable $occurredAt,
    ): void {
        if ($this->status !== TransactionStatus::PROCESSING) {
            throw InvalidTransactionTransition::acquirerAttemptWhile($this->status);
        }

        if ($attempt < 1) {
            throw InvalidAcquirerAttempt::nonPositiveAttempt($attempt);
        }

        if ($durationMs < 0) {
            throw InvalidAcquirerAttempt::negativeDuration($durationMs);
        }

        $this->recordedEvents[] = TransactionEvent::acquirerAttempt(
            ++$this->lastEventSequence,
            $attempt,
            $outcome,
            $durationMs,
            $errorCode,
            $this->correlationId,
            $occurredAt,
        );
    }

    /**
     * Events recorded since they were last cleared, in order.
     *
     * @return list<TransactionEvent>
     */
    public function recordedEvents(): array
    {
        return $this->recordedEvents;
    }

    /**
     * Called once the recorded events are persisted, so a failed save keeps them for a retry.
     */
    public function clearRecordedEvents(): void
    {
        $this->recordedEvents = [];
    }

    public function id(): TransactionId
    {
        return $this->id;
    }

    public function merchantId(): MerchantId
    {
        return $this->merchantId;
    }

    public function money(): Money
    {
        return $this->money;
    }

    public function card(): MaskedCard
    {
        return $this->card;
    }

    public function status(): TransactionStatus
    {
        return $this->status;
    }

    public function declineReason(): ?DeclineReason
    {
        return $this->declineReason;
    }

    public function acquirerReference(): ?string
    {
        return $this->acquirerReference;
    }

    public function correlationId(): CorrelationId
    {
        return $this->correlationId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    private function transitionTo(TransactionStatus $next, ?DeclineReason $reason, DateTimeImmutable $occurredAt): void
    {
        if (! $this->status->canTransitionTo($next)) {
            throw InvalidTransactionTransition::between($this->status, $next);
        }

        $this->recordedEvents[] = TransactionEvent::statusChanged(
            ++$this->lastEventSequence,
            $this->status,
            $next,
            $reason,
            $this->correlationId,
            $occurredAt,
        );
        $this->status = $next;
        $this->declineReason = $reason;
    }
}
