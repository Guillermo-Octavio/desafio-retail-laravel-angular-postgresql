<?php

declare(strict_types=1);

namespace App\Payments\Domain\Exceptions;

use Throwable;

/**
 * Unexpected acquirer response, such as a 4xx: it is not retried because repeating the
 * same request would fail again.
 */
final class AcquirerIntegrationFailed extends PaymentsDomainException
{
    public function __construct(
        public readonly string $errorCode,
        ?Throwable $previous = null,
    ) {
        parent::__construct("The acquirer rejected the integration request ({$errorCode}).", 0, $previous);
    }
}
