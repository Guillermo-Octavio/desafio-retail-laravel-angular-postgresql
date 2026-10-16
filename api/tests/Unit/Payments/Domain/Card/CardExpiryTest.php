<?php

declare(strict_types=1);

use App\Payments\Domain\Card\CardExpiry;
use App\Payments\Domain\Exceptions\InvalidCardData;

it('holds a month and a four digit year', function () {
    $expiry = new CardExpiry(12, 2030);

    expect($expiry->month)->toBe(12)
        ->and($expiry->year)->toBe(2030);
});

it('rejects an invalid month or year', function (int $month, int $year) {
    new CardExpiry($month, $year);
})->with([
    'month 0' => [0, 2030],
    'month 13' => [13, 2030],
    'two digit year' => [12, 30],
    'five digit year' => [12, 20300],
])->throws(InvalidCardData::class);

it('is valid until the last day of its month', function (int $month, int $year, bool $expired) {
    $today = new DateTimeImmutable('2026-10-15 12:00:00', new DateTimeZone('UTC'));

    expect((new CardExpiry($month, $year))->isExpiredAt($today, new DateTimeZone('America/Santiago')))->toBe($expired);
})->with([
    'previous month' => [9, 2026, true],
    'previous year' => [12, 2025, true],
    'current month' => [10, 2026, false],
    'next month' => [11, 2026, false],
    'next year, earlier month' => [1, 2027, false],
]);

it('evaluates the current month in the business timezone', function () {
    // 01:00 on November 1st in UTC is still 22:00 on October 31st in Santiago.
    $now = new DateTimeImmutable('2026-11-01 01:00:00', new DateTimeZone('UTC'));
    $expiresInOctober = new CardExpiry(10, 2026);

    expect($expiresInOctober->isExpiredAt($now, new DateTimeZone('America/Santiago')))->toBeFalse()
        ->and($expiresInOctober->isExpiredAt($now, new DateTimeZone('UTC')))->toBeTrue();
});
