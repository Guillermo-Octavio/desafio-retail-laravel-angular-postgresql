<?php

declare(strict_types=1);

use App\Payments\Domain\Exceptions\InvalidIdempotencyKey;
use App\Payments\Domain\Exceptions\InvalidRequestFingerprint;
use App\Payments\Domain\Idempotency\IdempotencyKey;
use App\Payments\Domain\Idempotency\IdempotencyReservation;
use App\Payments\Domain\Idempotency\RequestFingerprint;
use App\Payments\Domain\Idempotency\ReservationOutcome;
use App\Payments\Domain\Idempotency\StoredResponse;
use Tests\Support\PaymentFixtures;

it('accepts an idempotency key of up to 255 characters', function () {
    expect((new IdempotencyKey(str_repeat('k', 255)))->value)->toBe(str_repeat('k', 255));
});

it('rejects an empty or too long idempotency key', function (string $value) {
    new IdempotencyKey($value);
})->with(['', str_repeat('k', 256)])->throws(InvalidIdempotencyKey::class);

it('accepts a request fingerprint of 64 hexadecimal characters', function () {
    $hmac = hash_hmac('sha256', 'content', 'secret');

    expect((new RequestFingerprint($hmac))->value)->toBe($hmac);
});

it('rejects a request fingerprint that is not a SHA-256 hex digest', function (string $value) {
    new RequestFingerprint($value);
})->with([
    'too short' => [str_repeat('a', 63)],
    'uppercase' => [str_repeat('A', 64)],
    'not hexadecimal' => [str_repeat('g', 64)],
])->throws(InvalidRequestFingerprint::class);

it('reserves a key that did not exist', function () {
    $reservation = IdempotencyReservation::reserved();

    expect($reservation->outcome)->toBe(ReservationOutcome::RESERVED)
        ->and($reservation->storedResponse)->toBeNull();
});

it('replays the stored response when the same key arrives with the same content', function () {
    $response = new StoredResponse(201, ['status' => 'APPROVED']);

    $reservation = IdempotencyReservation::forExistingKey(PaymentFixtures::fingerprint('a'), PaymentFixtures::fingerprint('a'), $response);

    expect($reservation->outcome)->toBe(ReservationOutcome::REPLAY)
        ->and($reservation->storedResponse)->toBe($response);
});

it('reports a conflict when the same key arrives with different content', function () {
    $reservation = IdempotencyReservation::forExistingKey(PaymentFixtures::fingerprint('a'), PaymentFixtures::fingerprint('b'), new StoredResponse(201, []));

    expect($reservation->outcome)->toBe(ReservationOutcome::FINGERPRINT_MISMATCH)
        ->and($reservation->storedResponse)->toBeNull();
});

it('reports a key whose first request is still in progress', function () {
    $reservation = IdempotencyReservation::forExistingKey(PaymentFixtures::fingerprint('a'), PaymentFixtures::fingerprint('a'), null);

    expect($reservation->outcome)->toBe(ReservationOutcome::IN_PROGRESS)
        ->and($reservation->storedResponse)->toBeNull();
});

it('reports a content conflict before an in progress key', function () {
    $reservation = IdempotencyReservation::forExistingKey(PaymentFixtures::fingerprint('a'), PaymentFixtures::fingerprint('b'), null);

    expect($reservation->outcome)->toBe(ReservationOutcome::FINGERPRINT_MISMATCH);
});
