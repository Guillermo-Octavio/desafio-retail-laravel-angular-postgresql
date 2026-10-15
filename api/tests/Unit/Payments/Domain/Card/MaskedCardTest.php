<?php

declare(strict_types=1);

use App\Payments\Domain\Card\CardBrand;
use App\Payments\Domain\Card\MaskedCard;
use App\Payments\Domain\Exceptions\InvalidCardData;

it('holds the brand and the last four digits', function () {
    $card = new MaskedCard(CardBrand::AMEX, '0005');

    expect($card->brand)->toBe(CardBrand::AMEX)
        ->and($card->last4)->toBe('0005');
});

it('rejects anything other than four digits', function (string $last4) {
    new MaskedCard(CardBrand::VISA, $last4);
})->with(['111', '11111', '11a1'])->throws(InvalidCardData::class);
