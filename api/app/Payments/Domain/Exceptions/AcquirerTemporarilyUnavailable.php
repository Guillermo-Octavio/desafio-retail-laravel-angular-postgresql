<?php

declare(strict_types=1);

namespace App\Payments\Domain\Exceptions;

use Throwable;

/**
 * Timeout, 5xx or connection failure: the only acquirer error that is retried (SUP-10).
 */
final class AcquirerTemporarilyUnavailable extends PaymentsDomainException
{
    public function __construct(
        public readonly string $errorCode,
        ?Throwable $previous = null,
    ) {
        parent::__construct("The acquirer is temporarily unavailable ({$errorCode}).", 0, $previous);
    }
}
