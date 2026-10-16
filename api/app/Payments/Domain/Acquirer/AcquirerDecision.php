<?php

declare(strict_types=1);

namespace App\Payments\Domain\Acquirer;

use App\Payments\Domain\AcquirerOutcome;
use App\Payments\Domain\DeclineReason;

/**
 * A definitive answer from the acquirer: it is never retried.
 */
final readonly class AcquirerDecision
{
    private function __construct(
        public AcquirerOutcome $outcome,
        public ?string $authorizationReference,
        public ?DeclineReason $declineReason,
    ) {}

    public static function approved(string $authorizationReference): self
    {
        return new self(AcquirerOutcome::APPROVED, $authorizationReference, null);
    }

    public static function declined(DeclineReason $reason): self
    {
        return new self(AcquirerOutcome::DECLINED, null, $reason);
    }
}
