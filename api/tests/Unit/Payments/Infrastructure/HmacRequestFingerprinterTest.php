<?php

declare(strict_types=1);

use App\Payments\Domain\Currency;
use App\Payments\Infrastructure\Exceptions\MissingFingerprintSecret;
use App\Payments\Infrastructure\HmacRequestFingerprinter;
use Tests\Support\PaymentFixtures;

beforeEach(function () {
    $this->fingerprinter = new HmacRequestFingerprinter('base64:test-secret');
});

it('gives the same fingerprint to the same content', function () {
    expect($this->fingerprinter->fingerprint(PaymentFixtures::command())->value)
        ->toBe($this->fingerprinter->fingerprint(PaymentFixtures::command())->value);
});

it('ignores the idempotency key and the correlation id, which are not part of the content', function () {
    $original = $this->fingerprinter->fingerprint(PaymentFixtures::command());
    $retry = $this->fingerprinter->fingerprint(PaymentFixtures::command(idempotencyKey: 'key-002', correlationId: 'corr-999'));

    expect($retry->equals($original))->toBeTrue();
});

it('changes the fingerprint when any field of the content changes', function (array $change) {
    $original = $this->fingerprinter->fingerprint(PaymentFixtures::command());

    expect($this->fingerprinter->fingerprint(PaymentFixtures::command(...$change))->equals($original))->toBeFalse();
})->with([
    'merchant' => [['merchantId' => 'merchant-002']],
    'amount' => [['amount' => 20000]],
    'currency' => [['currency' => Currency::USD]],
    'card number' => [['cardNumber' => '5555555555554444']],
    'holder name' => [['holderName' => 'Grace Hopper']],
    'expiry month' => [['expiryMonth' => 11]],
    'expiry year' => [['expiryYear' => 2031]],
    'cvv' => [['cvv' => '456']],
]);

it('signs the content with the application secret', function () {
    $other = new HmacRequestFingerprinter('base64:another-secret');

    expect($other->fingerprint(PaymentFixtures::command())->equals($this->fingerprinter->fingerprint(PaymentFixtures::command())))
        ->toBeFalse();
});

it('requires a secret', function () {
    new HmacRequestFingerprinter('');
})->throws(MissingFingerprintSecret::class);
