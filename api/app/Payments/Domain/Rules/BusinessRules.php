<?php

declare(strict_types=1);

namespace App\Payments\Domain\Rules;

use App\Payments\Domain\DeclineReason;
use App\Payments\Domain\PaymentRequest;
use App\Payments\Domain\Ports\Clock;

/**
 * Evaluates the rules in order and stops at the first one that fails (DD-07).
 */
final readonly class BusinessRules
{
    /** @var list<BusinessRule> */
    private array $rules;

    public function __construct(BusinessRule ...$rules)
    {
        $this->rules = array_values($rules);
    }

    public static function standard(Clock $clock, AmountLimits $limits): self
    {
        return new self(
            new ValidCardNumberRule,
            new CardNotExpiredRule($clock),
            new AmountWithinLimitRule($limits),
        );
    }

    public function firstViolation(PaymentRequest $payment): ?DeclineReason
    {
        foreach ($this->rules as $rule) {
            $reason = $rule->check($payment);

            if ($reason !== null) {
                return $reason;
            }
        }

        return null;
    }
}
