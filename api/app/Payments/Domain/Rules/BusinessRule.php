<?php

declare(strict_types=1);

namespace App\Payments\Domain\Rules;

use App\Payments\Domain\DeclineReason;
use App\Payments\Domain\PaymentRequest;

interface BusinessRule
{
    /**
     * Returns the reason to decline the payment, or null when the rule is met.
     */
    public function check(PaymentRequest $payment): ?DeclineReason;
}
