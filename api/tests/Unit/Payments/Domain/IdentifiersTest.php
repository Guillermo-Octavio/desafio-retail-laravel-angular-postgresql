<?php

declare(strict_types=1);

use App\Payments\Domain\CorrelationId;
use App\Payments\Domain\Exceptions\InvalidCorrelationId;
use App\Payments\Domain\Exceptions\InvalidTransactionId;
use App\Payments\Domain\TransactionId;

it('accepts a UUID v7 as transaction id and normalizes it to lowercase', function () {
    expect((new TransactionId('0199F0C2-6A1E-7C3A-9B2F-5D1E8A4C7B10'))->value)
        ->toBe('0199f0c2-6a1e-7c3a-9b2f-5d1e8a4c7b10');
});

it('rejects a transaction id that is not a UUID v7', function (string $value) {
    new TransactionId($value);
})->with([
    'UUID v4' => ['9b2f5d1e-8a4c-4b10-9b2f-5d1e8a4c7b10'],
    'not a UUID' => ['transaction-1'],
    'empty' => [''],
])->throws(InvalidTransactionId::class);

it('compares transaction ids by value', function () {
    $id = new TransactionId('0199f0c2-6a1e-7c3a-9b2f-5d1e8a4c7b10');

    expect($id->equals(new TransactionId('0199F0C2-6A1E-7C3A-9B2F-5D1E8A4C7B10')))->toBeTrue()
        ->and($id->equals(new TransactionId('0199f0c2-6a1e-7c3a-9b2f-5d1e8a4c7b11')))->toBeFalse();
});

it('accepts a correlation id of up to 64 characters', function () {
    expect((new CorrelationId(str_repeat('c', 64)))->value)->toBe(str_repeat('c', 64));
});

it('rejects an empty or too long correlation id', function (string $value) {
    new CorrelationId($value);
})->with(['', str_repeat('c', 65)])->throws(InvalidCorrelationId::class);

it('builds a transaction id only from a UUID v7, without throwing', function () {
    expect(TransactionId::tryFrom('0199F0C2-6A1E-7C3A-9B2F-5D1E8A4C7B10')?->value)->toBe('0199f0c2-6a1e-7c3a-9b2f-5d1e8a4c7b10')
        ->and(TransactionId::tryFrom('no-es-un-uuid'))->toBeNull()
        ->and(TransactionId::tryFrom('00000000-0000-0000-0000-000000000000'))->toBeNull();
});
