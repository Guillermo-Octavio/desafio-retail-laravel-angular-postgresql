<?php

declare(strict_types=1);

namespace App\Payments\Domain\Card;

use App\Payments\Domain\Exceptions\InvalidCardData;
use DateTimeImmutable;
use DateTimeZone;

final readonly class CardExpiry
{
    private const int MONTHS_PER_YEAR = 12;

    private const int MIN_YEAR = 1000;

    private const int MAX_YEAR = 9999;

    public function __construct(
        public int $month,
        public int $year,
    ) {
        if ($month < 1 || $month > self::MONTHS_PER_YEAR) {
            throw InvalidCardData::invalidExpiryMonth($month);
        }

        if ($year < self::MIN_YEAR || $year > self::MAX_YEAR) {
            throw InvalidCardData::invalidExpiryYear($year);
        }
    }

    /**
     * A card stays valid until the last day of its expiry month, evaluated in the business
     * timezone, so the month ends when it ends for the merchant (DD-06).
     */
    public function isExpiredAt(DateTimeImmutable $now, DateTimeZone $businessTimezone): bool
    {
        $today = $now->setTimezone($businessTimezone);
        $currentMonthIndex = (int) $today->format('Y') * self::MONTHS_PER_YEAR + (int) $today->format('n');

        return $this->year * self::MONTHS_PER_YEAR + $this->month < $currentMonthIndex;
    }
}
