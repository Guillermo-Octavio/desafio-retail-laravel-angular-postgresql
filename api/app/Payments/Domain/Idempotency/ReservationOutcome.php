<?php

declare(strict_types=1);

namespace App\Payments\Domain\Idempotency;

enum ReservationOutcome: string
{
    case RESERVED = 'RESERVED';
    case REPLAY = 'REPLAY';
    case FINGERPRINT_MISMATCH = 'FINGERPRINT_MISMATCH';
    case IN_PROGRESS = 'IN_PROGRESS';
}
