<?php

declare(strict_types=1);

namespace App\Payments\Domain\Rules;

use App\Payments\Domain\DeclineReason;
use App\Payments\Domain\PaymentRequest;
use App\Payments\Domain\Ports\Clock;
use DateTimeZone;

final readonly class CardNotExpiredRule implements BusinessRule
{
    public function __construct(
        private Clock $clock,
        private DateTimeZone $businessTimezone,
    ) {}

    public function check(PaymentRequest $payment): ?DeclineReason
    {
        return $payment->card->expiry->isExpiredAt($this->clock->now(), $this->businessTimezone) ? DeclineReason::CARD_EXPIRED : null;
    }
}
