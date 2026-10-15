<?php

declare(strict_types=1);

namespace App\Payments\Domain\Card;

/**
 * Transient card data received in a payment request; only its masked form is persisted.
 */
final readonly class CardDetails
{
    public function __construct(
        public CardNumber $number,
        public CardExpiry $expiry,
        public Cvv $cvv,
    ) {}
}
