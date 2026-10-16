<?php

declare(strict_types=1);

namespace App\Payments\Application\Ports;

use App\Payments\Application\ProcessPaymentCommand;
use App\Payments\Domain\Idempotency\RequestFingerprint;

interface RequestFingerprinter
{
    /**
     * Fingerprints the payment content, excluding the idempotency key and the correlation id,
     * so a retry of the same request matches the original (SUP-11).
     */
    public function fingerprint(ProcessPaymentCommand $command): RequestFingerprint;
}
