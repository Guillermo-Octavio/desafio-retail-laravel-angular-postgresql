<?php

declare(strict_types=1);

use App\Payments\Domain\Exceptions\AcquirerIntegrationFailed;
use App\Payments\Domain\Exceptions\AcquirerTemporarilyUnavailable;

it('keeps the error code and the original cause of an acquirer error', function (string $exceptionClass, string $errorCode) {
    $cause = new RuntimeException('connection reset');

    $exception = new $exceptionClass($errorCode, $cause);

    expect($exception->errorCode)->toBe($errorCode)
        ->and($exception->getMessage())->toContain($errorCode)
        ->and($exception->getPrevious())->toBe($cause);
})->with([
    'temporary error' => [AcquirerTemporarilyUnavailable::class, 'TIMEOUT'],
    'integration error' => [AcquirerIntegrationFailed::class, 'HTTP_422'],
]);
