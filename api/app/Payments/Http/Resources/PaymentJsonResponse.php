<?php

declare(strict_types=1);

namespace App\Payments\Http\Resources;

use App\Payments\Application\PaymentResponse;
use Illuminate\Http\JsonResponse;

final class PaymentJsonResponse
{
    public const string REPLAYED_HEADER = 'Idempotent-Replayed';

    public static function from(PaymentResponse $payment): JsonResponse
    {
        $headers = $payment->replayed ? [self::REPLAYED_HEADER => 'true'] : [];

        return new JsonResponse($payment->response->body, $payment->response->statusCode, $headers);
    }
}
