<?php

declare(strict_types=1);

use App\Payments\Domain\Currency;
use App\Payments\Domain\Exceptions\CurrencyMismatch;
use App\Payments\Domain\Exceptions\InvalidAmount;
use App\Payments\Domain\Money;

it('exposes the ISO 4217 exponent of each supported currency', function (Currency $currency, int $exponent) {
    expect($currency->exponent())->toBe($exponent);
})->with([
    'CLP has no decimals' => [Currency::CLP, 0],
    'USD has cents' => [Currency::USD, 2],
]);

it('supports only CLP and USD', function () {
    expect(Currency::cases())->toBe([Currency::CLP, Currency::USD])
        ->and(Currency::tryFrom('EUR'))->toBeNull();
});

it('holds a positive amount in minor units with its currency', function () {
    $money = new Money(1050, Currency::USD);

    expect($money->amount)->toBe(1050)
        ->and($money->currency)->toBe(Currency::USD);
});

it('rejects an amount that is not positive', function (int $amount) {
    new Money($amount, Currency::CLP);
})->with([0, -1])->throws(InvalidAmount::class);

it('compares by amount and currency', function () {
    $money = new Money(15000, Currency::CLP);

    expect($money->equals(new Money(15000, Currency::CLP)))->toBeTrue()
        ->and($money->equals(new Money(15001, Currency::CLP)))->toBeFalse()
        ->and($money->equals(new Money(15000, Currency::USD)))->toBeFalse();
});

it('tells whether it is greater than another amount in the same currency', function () {
    $limit = new Money(5000000, Currency::CLP);

    expect((new Money(5000001, Currency::CLP))->isGreaterThan($limit))->toBeTrue()
        ->and((new Money(5000000, Currency::CLP))->isGreaterThan($limit))->toBeFalse();
});

it('never compares amounts in different currencies', function () {
    (new Money(100, Currency::CLP))->isGreaterThan(new Money(1, Currency::USD));
})->throws(CurrencyMismatch::class);
