<?php

declare(strict_types=1);

use App\Payments\Domain\Card\Cvv;
use App\Payments\Domain\Exceptions\InvalidCardData;
use App\Payments\Domain\Exceptions\SensitiveDataNotSerializable;

it('accepts three or four digits', function (string $cvv) {
    expect((new Cvv($cvv))->reveal())->toBe($cvv);
})->with(['123', '1234']);

it('rejects a value that is not three or four digits', function (string $cvv) {
    new Cvv($cvv);
})->with(['12', '12345', '12a', ''])->throws(InvalidCardData::class);

it('never includes the value in the exception message', function () {
    try {
        new Cvv('98a');
    } catch (InvalidCardData $exception) {
        expect($exception->getMessage())->not->toContain('98');

        return;
    }

    test()->fail('InvalidCardData was not thrown.');
});

it('never shows the value when dumped', function () {
    $cvv = new Cvv('987');

    ob_start();
    var_dump($cvv);
    $dump = ob_get_clean().print_r($cvv, true).json_encode($cvv);

    expect($dump)->not->toContain('987');
});

it('cannot be serialized', function () {
    serialize(new Cvv('987'));
})->throws(SensitiveDataNotSerializable::class);
