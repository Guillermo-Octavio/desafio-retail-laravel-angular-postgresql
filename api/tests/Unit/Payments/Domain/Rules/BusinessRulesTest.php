<?php

declare(strict_types=1);

use App\Payments\Domain\Card\CardDetails;
use App\Payments\Domain\Card\CardExpiry;
use App\Payments\Domain\Card\CardNumber;
use App\Payments\Domain\Card\Cvv;
use App\Payments\Domain\Currency;
use App\Payments\Domain\DeclineReason;
use App\Payments\Domain\Exceptions\InvalidAmountLimits;
use App\Payments\Domain\MerchantId;
use App\Payments\Domain\Money;
use App\Payments\Domain\PaymentRequest;
use App\Payments\Domain\Rules\AmountLimits;
use App\Payments\Domain\Rules\AmountWithinLimitRule;
use App\Payments\Domain\Rules\BusinessRule;
use App\Payments\Domain\Rules\BusinessRules;
use App\Payments\Domain\Rules\CardNotExpiredRule;
use App\Payments\Domain\Rules\ValidCardNumberRule;
use Tests\Support\FixedClock;

function paymentRequest(
    int $amount = 15000,
    Currency $currency = Currency::CLP,
    string $cardNumber = '4111111111111111',
    int $expiryMonth = 12,
    int $expiryYear = 2030,
): PaymentRequest {
    return new PaymentRequest(
        new MerchantId('merchant-001'),
        new Money($amount, $currency),
        new CardDetails(new CardNumber($cardNumber), new CardExpiry($expiryMonth, $expiryYear), new Cvv('123')),
    );
}

function amountLimits(): AmountLimits
{
    return new AmountLimits(['CLP' => 5000000, 'USD' => 500000]);
}

function standardRules(): BusinessRules
{
    return BusinessRules::standard(new FixedClock('2026-10-15 12:00:00'), amountLimits());
}

it('declines a card number that fails the Luhn check', function () {
    $rule = new ValidCardNumberRule;

    expect($rule->check(paymentRequest(cardNumber: '4111111111111112')))->toBe(DeclineReason::INVALID_CARD_NUMBER)
        ->and($rule->check(paymentRequest()))->toBeNull();
});

it('declines a card that expired before the current month', function (int $month, int $year, ?DeclineReason $reason) {
    $rule = new CardNotExpiredRule(new FixedClock('2026-10-15 12:00:00'));

    expect($rule->check(paymentRequest(expiryMonth: $month, expiryYear: $year)))->toBe($reason);
})->with([
    'expired last month' => [9, 2026, DeclineReason::CARD_EXPIRED],
    'expires this month' => [10, 2026, null],
]);

it('declines an amount above the limit of its currency', function (Currency $currency, int $amount, ?DeclineReason $reason) {
    $rule = new AmountWithinLimitRule(amountLimits());

    expect($rule->check(paymentRequest(amount: $amount, currency: $currency)))->toBe($reason);
})->with([
    'CLP at the limit' => [Currency::CLP, 5000000, null],
    'CLP above the limit' => [Currency::CLP, 5000001, DeclineReason::AMOUNT_LIMIT_EXCEEDED],
    'USD with cents' => [Currency::USD, 1050, null],
    'USD at the limit' => [Currency::USD, 500000, null],
    'USD above the limit' => [Currency::USD, 500001, DeclineReason::AMOUNT_LIMIT_EXCEEDED],
]);

it('requires a positive limit for every supported currency', function (array $limits) {
    new AmountLimits($limits);
})->with([
    'missing USD' => [['CLP' => 5000000]],
    'zero limit' => [['CLP' => 5000000, 'USD' => 0]],
    'limit given as text' => [['CLP' => '5000000', 'USD' => 500000]],
    'unsupported currency' => [['CLP' => 5000000, 'USD' => 500000, 'EUR' => 1]],
])->throws(InvalidAmountLimits::class);

it('applies the standard rules in order: card number, expiry, amount', function (PaymentRequest $payment, ?DeclineReason $reason) {
    expect(standardRules()->firstViolation($payment))->toBe($reason);
})->with([
    'all rules fail' => fn () => [paymentRequest(amount: 5000001, cardNumber: '4111111111111112', expiryMonth: 9, expiryYear: 2026), DeclineReason::INVALID_CARD_NUMBER],
    'expiry and amount fail' => fn () => [paymentRequest(amount: 5000001, expiryMonth: 9, expiryYear: 2026), DeclineReason::CARD_EXPIRED],
    'only amount fails' => fn () => [paymentRequest(amount: 5000001), DeclineReason::AMOUNT_LIMIT_EXCEEDED],
    'all rules pass' => fn () => [paymentRequest(), null],
]);

it('stops at the first rule that fails', function () {
    $neverReached = new class implements BusinessRule
    {
        public bool $checked = false;

        public function check(PaymentRequest $payment): ?DeclineReason
        {
            $this->checked = true;

            return null;
        }
    };

    $rules = new BusinessRules(new ValidCardNumberRule, $neverReached);

    expect($rules->firstViolation(paymentRequest(cardNumber: '4111111111111112')))->toBe(DeclineReason::INVALID_CARD_NUMBER)
        ->and($neverReached->checked)->toBeFalse();
});
