<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Illuminate\Http\JsonResponse;

/**
 * The single error format of the API (architecture section 8).
 */
final class ErrorResponse
{
    /**
     * @param  array<string, list<string>>|null  $details
     */
    public static function make(int $status, string $code, string $message, ?array $details = null): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];

        if ($details !== null) {
            $error['details'] = $details;
        }

        return new JsonResponse(['error' => $error], $status);
    }
}
