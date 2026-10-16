<?php

declare(strict_types=1);

namespace App\Payments\Http;

use App\Payments\Application\Exceptions\IdempotencyKeyInProgress;
use App\Payments\Application\Exceptions\IdempotencyKeyReused;
use App\Payments\Http\Exceptions\MissingIdempotencyKey;
use App\Shared\Http\ApiErrorRendering;
use App\Shared\Http\ErrorResponse;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Maps the payments errors to the API error codes (requirements analysis, section 8).
 */
final class PaymentsErrorRendering
{
    public static function register(Exceptions $exceptions): void
    {
        // Expected client errors: they are answered with a 4xx and are not application failures.
        $exceptions->dontReport([MissingIdempotencyKey::class, IdempotencyKeyReused::class, IdempotencyKeyInProgress::class]);

        $exceptions->render(fn (MissingIdempotencyKey $error, Request $request): ?JsonResponse => ApiErrorRendering::forApi($request, fn () => ErrorResponse::make(
            JsonResponse::HTTP_BAD_REQUEST,
            'IDEMPOTENCY_KEY_MISSING',
            $error->getMessage(),
        )));

        $exceptions->render(fn (IdempotencyKeyReused $error, Request $request): ?JsonResponse => ApiErrorRendering::forApi($request, fn () => ErrorResponse::make(
            JsonResponse::HTTP_CONFLICT,
            'IDEMPOTENCY_KEY_REUSED',
            $error->getMessage(),
        )));

        $exceptions->render(fn (IdempotencyKeyInProgress $error, Request $request): ?JsonResponse => ApiErrorRendering::forApi($request, fn () => ErrorResponse::make(
            JsonResponse::HTTP_CONFLICT,
            'IDEMPOTENCY_KEY_IN_PROGRESS',
            $error->getMessage(),
        )));
    }
}
