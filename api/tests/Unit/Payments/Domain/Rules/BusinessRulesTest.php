<?php

declare(strict_types=1);

use App\Payments\Domain\Currency;
use App\Payments\Domain\DeclineReason;
use App\Payments\Domain\Exceptions\InvalidAmountLimits;
use App\Payments\Domain\PaymentRequest;
use App\Payments\Domain\Rules\AmountLimits;
use App\Payments\Domain\Rules\AmountWithinLimitRule;
use App\Payments\Domain\Rules\BusinessRule;
use App\Payments\Domain\Rules\BusinessRules;
use App\Payments\Domain\Rules\CardNotExpiredRule;
use App\Payments\Domain\Rules\ValidCardNumberRule;
use Tests\Support\FixedClock;
use Tests\Support\PaymentFixtures;

it('declines a card number that fails the Luhn check', function () {
    $rule = new ValidCardNumberRule;

    expect($rule->check(PaymentFixtures::paymentRequest(cardNumber: PaymentFixtures::LUHN_INVALID_CARD)))->toBe(DeclineReason::INVALID_CARD_NUMBER)
        ->and($rule->check(PaymentFixtures::paymentRequest()))->toBeNull();
});

it('declines a card that expired before the current month', function (int $month, int $year, ?DeclineReason $reason) {
    $rule = new CardNotExpiredRule(new FixedClock('2026-10-15 12:00:00'), new DateTimeZone('America/Santiago'));

    expect($rule->check(PaymentFixtures::paymentRequest(expiryMonth: $month, expiryYear: $year)))->toBe($reason);
})->with([
    'expired last month' => [9, 2026, DeclineReason::CARD_EXPIRED],
    'expires this month' => [10, 2026, null],
]);

it('declines an amount above the limit of its currency', function (Currency $currency, int $amount, ?DeclineReason $reason) {
    $rule = new AmountWithinLimitRule(new AmountLimits(['CLP' => 5000000, 'USD' => 500000]));

    expect($rule->check(PaymentFixtures::paymentRequest(amount: $amount, currency: $currency)))->toBe($reason);
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
    expect(BusinessRules::standard(new FixedClock('2026-10-15 12:00:00'), new DateTimeZone('America/Santiago'), new AmountLimits(['CLP' => 5000000, 'USD' => 500000]))->firstViolation($payment))->toBe($reason);
})->with([
    'all rules fail' => fn () => [PaymentFixtures::paymentRequest(amount: 5000001, cardNumber: PaymentFixtures::LUHN_INVALID_CARD, expiryMonth: 9, expiryYear: 2026), DeclineReason::INVALID_CARD_NUMBER],
    'expiry and amount fail' => fn () => [PaymentFixtures::paymentRequest(amount: 5000001, expiryMonth: 9, expiryYear: 2026), DeclineReason::CARD_EXPIRED],
    'only amount fails' => fn () => [PaymentFixtures::paymentRequest(amount: 5000001), DeclineReason::AMOUNT_LIMIT_EXCEEDED],
    'all rules pass' => fn () => [PaymentFixtures::paymentRequest(), null],
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

    expect($rules->firstViolation(PaymentFixtures::paymentRequest(cardNumber: PaymentFixtures::LUHN_INVALID_CARD)))->toBe(DeclineReason::INVALID_CARD_NUMBER)
        ->and($neverReached->checked)->toBeFalse();
});

it('accepts a card on the last evening of its expiry month in Santiago, already the next month in UTC', function () {
    $rule = new CardNotExpiredRule(new FixedClock('2026-11-01 01:00:00'), new DateTimeZone('America/Santiago'));

    expect($rule->check(PaymentFixtures::paymentRequest(expiryMonth: 10, expiryYear: 2026)))->toBeNull();
});
