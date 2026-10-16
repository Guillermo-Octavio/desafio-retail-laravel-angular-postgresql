<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Renders the errors every API module shares. Module-specific errors are registered before
 * these, because the last callback catches anything left as an internal error.
 */
final class ApiErrorRendering
{
    private const string API_PATHS = 'api/*';

    public static function isApiRequest(Request $request): bool
    {
        return $request->is(self::API_PATHS);
    }

    public static function register(Exceptions $exceptions): void
    {
        // Every module reports its unexpected failures as one named event (architecture 7.2).
        // It replaces the default report; the JSON formatter keeps the exception class and
        // message without the stack trace.
        $exceptions->report(function (Throwable $error): void {
            Log::error('unexpected_error', ['exception' => $error]);
        })->stop();

        $exceptions->render(fn (ValidationException $error, Request $request): ?JsonResponse => self::forApi($request, fn () => ErrorResponse::make(
            JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            'VALIDATION_ERROR',
            'The given data was invalid.',
            $error->errors(),
        )));

        $exceptions->render(fn (NotFoundHttpException $error, Request $request): ?JsonResponse => self::forApi($request, fn () => ErrorResponse::make(
            JsonResponse::HTTP_NOT_FOUND,
            'NOT_FOUND',
            'The requested resource was not found.',
        )));

        $exceptions->render(fn (HttpExceptionInterface $error, Request $request): ?JsonResponse => self::forApi($request, fn () => ErrorResponse::make(
            $error->getStatusCode(),
            'HTTP_ERROR',
            'The request could not be handled.',
        )));

        // Never exposes the exception message: it may hold internal details or sensitive data.
        // A response the code chose explicitly is left to Laravel, which returns it as is.
        $exceptions->render(fn (Throwable $error, Request $request): ?JsonResponse => $error instanceof HttpResponseException
            ? null
            : self::forApi($request, fn () => ErrorResponse::make(
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR,
                'INTERNAL_ERROR',
                'An unexpected error occurred.',
            )));
    }

    /**
     * @param  callable(): JsonResponse  $render
     */
    public static function forApi(Request $request, callable $render): ?JsonResponse
    {
        return self::isApiRequest($request) ? $render() : null;
    }
}
