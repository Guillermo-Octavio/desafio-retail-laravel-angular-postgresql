<?php

declare(strict_types=1);

use App\Payments\Domain\Card\CardBrand;
use App\Payments\Domain\Card\CardNumber;
use App\Payments\Domain\Exceptions\InvalidCardData;
use App\Payments\Domain\Exceptions\SensitiveDataNotSerializable;

it('accepts between 13 and 19 digits', function (string $number) {
    expect((new CardNumber($number))->reveal())->toBe($number);
})->with(['4222222222222', '4111111111111111', '6011000990139424123']);

it('rejects a number that is not 13 to 19 digits', function (string $number) {
    new CardNumber($number);
})->with(['411111111111', '41111111111111111111', '4111 1111 1111 1111', '4111-1111-1111-111', ''])
    ->throws(InvalidCardData::class);

it('never includes the number in the exception message', function () {
    try {
        new CardNumber('41111111111a1111');
    } catch (InvalidCardData $exception) {
        expect($exception->getMessage())->not->toContain('41111111111');

        return;
    }

    test()->fail('InvalidCardData was not thrown.');
});

it('checks the number with the Luhn algorithm', function (string $number, bool $passes) {
    expect((new CardNumber($number))->passesLuhn())->toBe($passes);
})->with([
    'valid VISA' => ['4111111111111111', true],
    'valid MASTERCARD' => ['5555555555554444', true],
    'valid AMEX' => ['378282246310005', true],
    'last digit changed' => ['4111111111111112', false],
    'two digits swapped' => ['1411111111111111', false],
]);

it('derives the brand from the prefix', function (string $number, CardBrand $brand) {
    expect((new CardNumber($number))->brand())->toBe($brand);
})->with([
    'VISA starts with 4' => ['4111111111111111', CardBrand::VISA],
    'MASTERCARD 51' => ['5105105105105100', CardBrand::MASTERCARD],
    'MASTERCARD 55' => ['5555555555554444', CardBrand::MASTERCARD],
    'MASTERCARD 2221' => ['2221000000000009', CardBrand::MASTERCARD],
    'MASTERCARD 2720' => ['2720990000000007', CardBrand::MASTERCARD],
    'AMEX 34' => ['340000000000009', CardBrand::AMEX],
    'AMEX 37' => ['378282246310005', CardBrand::AMEX],
    '50 is unknown' => ['5000000000000009', CardBrand::UNKNOWN],
    '56 is unknown' => ['5600000000000003', CardBrand::UNKNOWN],
    '2220 is unknown' => ['2220000000000000', CardBrand::UNKNOWN],
    '2721 is unknown' => ['2721000000000008', CardBrand::UNKNOWN],
    '35 is unknown' => ['3530111333300000', CardBrand::UNKNOWN],
    'DISCOVER is unknown' => ['6011000990139424', CardBrand::UNKNOWN],
]);

it('exposes only the brand and the last four digits as a masked card', function () {
    $masked = (new CardNumber('4111111111111111'))->masked();

    expect($masked->brand)->toBe(CardBrand::VISA)
        ->and($masked->last4)->toBe('1111')
        ->and((new CardNumber('378282246310005'))->last4())->toBe('0005');
});

it('never shows the full number when dumped', function () {
    $card = new CardNumber('4111111111111111');

    ob_start();
    var_dump($card);
    $dump = ob_get_clean().print_r($card, true).json_encode($card);

    expect($dump)->not->toContain('4111111111111111');
});

it('cannot be serialized', function () {
    serialize(new CardNumber('4111111111111111'));
})->throws(SensitiveDataNotSerializable::class);
