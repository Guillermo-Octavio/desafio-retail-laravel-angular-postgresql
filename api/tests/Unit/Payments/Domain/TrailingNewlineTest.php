<?php

declare(strict_types=1);

use App\Payments\Domain\Card\CardBrand;
use App\Payments\Domain\Card\CardNumber;
use App\Payments\Domain\Card\Cvv;
use App\Payments\Domain\Card\MaskedCard;
use App\Payments\Domain\Exceptions\InvalidCardData;
use App\Payments\Domain\Exceptions\InvalidRequestFingerprint;
use App\Payments\Domain\Exceptions\InvalidTransactionId;
use App\Payments\Domain\Idempotency\RequestFingerprint;
use App\Payments\Domain\TransactionId;

// A "$" anchor also matches before a final line feed; every format must end at the real end.
it('rejects a value followed by a line feed', function (Closure $build, string $exception) {
    expect($build)->toThrow($exception);
})->with([
    'card number' => [fn () => new CardNumber("4111111111111111\n"), InvalidCardData::class],
    'cvv' => [fn () => new Cvv("123\n"), InvalidCardData::class],
    'last four digits' => [fn () => new MaskedCard(CardBrand::VISA, "1111\n"), InvalidCardData::class],
    'transaction id' => [fn () => new TransactionId("0199f0c2-6a1e-7c3a-9b2f-5d1e8a4c7b10\n"), InvalidTransactionId::class],
    'request fingerprint' => [fn () => new RequestFingerprint(str_repeat('a', 64)."\n"), InvalidRequestFingerprint::class],
]);
