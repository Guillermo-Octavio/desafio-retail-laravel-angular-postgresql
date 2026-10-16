<?php

declare(strict_types=1);

namespace App\Payments\Domain\Card;

use App\Payments\Domain\Exceptions\InvalidCardData;
use App\Payments\Domain\Exceptions\SensitiveDataNotSerializable;
use SensitiveParameter;

/**
 * Transient: the CVV is only sent to the acquirer and never persisted, logged or
 * serialized (DD-04).
 */
final readonly class Cvv
{
    public const string FORMAT = '/^\d{3,4}\z/';

    private string $digits;

    public function __construct(#[SensitiveParameter] string $digits)
    {
        if (preg_match(self::FORMAT, $digits) !== 1) {
            throw InvalidCardData::malformedCvv();
        }

        $this->digits = $digits;
    }

    public function reveal(): string
    {
        return $this->digits;
    }

    /**
     * @return array{}
     */
    public function __debugInfo(): array
    {
        return [];
    }

    /**
     * @return never
     */
    public function __serialize(): array
    {
        throw SensitiveDataNotSerializable::of(self::class);
    }
}
