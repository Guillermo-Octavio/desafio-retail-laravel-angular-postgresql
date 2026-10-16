<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the correlation id of the request (SUP-12), adds it to the log context (DA-07) and
 * returns it on every response, errors included (HU-10).
 */
final class AssignCorrelationId
{
    public const string HEADER = 'X-Correlation-Id';

    public const string CONTEXT_KEY = 'correlation_id';

    // Up to 64 visible ASCII characters: what an HTTP header allows and the column stores.
    private const string ACCEPTED_FORMAT = '/^[\x21-\x7E]{1,64}\z/';

    private const string REPLACED_LOG_EVENT = 'correlation_id.replaced';

    public function handle(Request $request, Closure $next): Response
    {
        $received = $request->header(self::HEADER);
        $isUsable = $received !== null && preg_match(self::ACCEPTED_FORMAT, $received) === 1;
        $correlationId = $isUsable ? $received : (string) Str::uuid();
        Context::add(self::CONTEXT_KEY, $correlationId);

        if ($received !== null && ! $isUsable) {
            // Logged after the context is set, so the line carries the new correlation id. Only
            // the length is logged: the value itself is untrusted input.
            Log::warning(self::REPLACED_LOG_EVENT, ['received_length' => strlen($received)]);
        }

        $response = $next($request);
        $response->headers->set(self::HEADER, $correlationId);

        return $response;
    }
}
