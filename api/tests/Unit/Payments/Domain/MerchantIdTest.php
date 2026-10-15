<?php

declare(strict_types=1);

use App\Payments\Domain\Exceptions\InvalidMerchantId;
use App\Payments\Domain\MerchantId;

it('holds a merchant id of up to 64 characters', function () {
    expect((new MerchantId(str_repeat('m', 64)))->value)->toBe(str_repeat('m', 64));
});

it('rejects an empty or too long merchant id', function (string $value) {
    new MerchantId($value);
})->with(['', str_repeat('m', 65)])->throws(InvalidMerchantId::class);
