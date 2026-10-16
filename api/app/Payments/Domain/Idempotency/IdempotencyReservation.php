<?php

declare(strict_types=1);

namespace App\Payments\Domain\Idempotency;

final readonly class IdempotencyReservation
{
    private function __construct(
        public ReservationOutcome $outcome,
        public ?StoredResponse $storedResponse,
    ) {}

    public static function reserved(): self
    {
        return new self(ReservationOutcome::RESERVED, null);
    }

    /**
     * Decides what to do with a key that already exists: different content is always a
     * conflict, and a key without a stored response is still being processed.
     */
    public static function forExistingKey(
        RequestFingerprint $storedFingerprint,
        RequestFingerprint $requestFingerprint,
        ?StoredResponse $storedResponse,
    ): self {
        if (! $storedFingerprint->equals($requestFingerprint)) {
            return new self(ReservationOutcome::FINGERPRINT_MISMATCH, null);
        }

        if ($storedResponse === null) {
            return new self(ReservationOutcome::IN_PROGRESS, null);
        }

        return new self(ReservationOutcome::REPLAY, $storedResponse);
    }
}
