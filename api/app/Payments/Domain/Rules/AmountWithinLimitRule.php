<?php

declare(strict_types=1);

namespace App\Payments\Domain\Rules;

use App\Payments\Domain\DeclineReason;
use App\Payments\Domain\PaymentRequest;

final readonly class AmountWithinLimitRule implements BusinessRule
{
    public function __construct(private AmountLimits $limits) {}

    public function check(PaymentRequest $payment): ?DeclineReason
    {
        $limit = $this->limits->for($payment->money->currency);

        return $payment->money->isGreaterThan($limit) ? DeclineReason::AMOUNT_LIMIT_EXCEEDED : null;
    }
}
