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

    expect((new CardExpiry($month, $year))->isExpiredAt($today))->toBe($expired);
})->with([
    'previous month' => [9, 2026, true],
    'previous year' => [12, 2025, true],
    'current month' => [10, 2026, false],
    'next month' => [11, 2026, false],
    'next year, earlier month' => [1, 2027, false],
]);

it('evaluates the current month in UTC', function () {
    $localTime = new DateTimeImmutable('2026-11-01 02:00:00', new DateTimeZone('+05:00'));

    expect((new CardExpiry(10, 2026))->isExpiredAt($localTime))->toBeFalse();
});
