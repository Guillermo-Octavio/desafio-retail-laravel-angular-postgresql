<?php

declare(strict_types=1);

namespace App\Payments\Http\Middleware;

use App\Payments\Http\Exceptions\MissingIdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs before the body is validated, so a request without a key answers 400 (HU-06).
 */
final class RequireIdempotencyKey
{
    public const string HEADER = 'Idempotency-Key';

    public function handle(Request $request, Closure $next): Response
    {
        if (trim((string) $request->header(self::HEADER)) === '') {
            throw MissingIdempotencyKey::create();
        }

        return $next($request);
    }
}
