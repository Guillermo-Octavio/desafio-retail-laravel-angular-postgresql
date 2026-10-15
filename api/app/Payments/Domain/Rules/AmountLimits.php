<?php

declare(strict_types=1);

namespace App\Payments\Domain\Rules;

use App\Payments\Domain\Currency;
use App\Payments\Domain\Exceptions\InvalidAmountLimits;
use App\Payments\Domain\Money;

/**
 * Maximum amount per currency, in minor units (SUP-04).
 */
final readonly class AmountLimits
{
    /** @var array<string, int> */
    private array $limits;

    /**
     * @param  array<string, mixed>  $limitsByCurrencyCode
     */
    public function __construct(array $limitsByCurrencyCode)
    {
        foreach (array_keys($limitsByCurrencyCode) as $code) {
            if (Currency::tryFrom($code) === null) {
                throw InvalidAmountLimits::unsupportedCurrency($code);
            }
        }

        foreach (Currency::cases() as $currency) {
            $limit = $limitsByCurrencyCode[$currency->value] ?? null;

            if (! is_int($limit) || $limit <= 0) {
                throw InvalidAmountLimits::missingFor($currency->value);
            }
        }

        $this->limits = $limitsByCurrencyCode;
    }

    public function for(Currency $currency): Money
    {
        return new Money($this->limits[$currency->value], $currency);
    }
}
