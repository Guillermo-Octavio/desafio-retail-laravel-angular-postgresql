<?php

declare(strict_types=1);

namespace App\Payments\Domain\Rules;

use App\Payments\Domain\DeclineReason;
use App\Payments\Domain\PaymentRequest;

final readonly class ValidCardNumberRule implements BusinessRule
{
    public function check(PaymentRequest $payment): ?DeclineReason
    {
        return $payment->card->number->passesLuhn() ? null : DeclineReason::INVALID_CARD_NUMBER;
    }
}
