<?php

declare(strict_types=1);

namespace App\Payments\Domain\Ports;

use App\Payments\Domain\Acquirer\AcquirerDecision;
use App\Payments\Domain\Acquirer\AcquirerRequest;
use App\Payments\Domain\Exceptions\AcquirerIntegrationFailed;
use App\Payments\Domain\Exceptions\AcquirerTemporarilyUnavailable;

interface AcquirerGateway
{
    /**
     * Makes a single authorization attempt; retries belong to the application layer (DA-05).
     *
     * @throws AcquirerTemporarilyUnavailable on a timeout, a 5xx or a connection failure.
     * @throws AcquirerIntegrationFailed on any other unexpected response.
     */
    public function authorize(AcquirerRequest $request): AcquirerDecision;
}
