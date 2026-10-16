<?php

declare(strict_types=1);

namespace App\Payments\Http\Controllers;

use App\Payments\Application\ProcessPayment;
use App\Payments\Http\Requests\CreatePaymentRequest;
use App\Payments\Http\Resources\PaymentJsonResponse;
use Illuminate\Http\JsonResponse;

final readonly class CreatePaymentController
{
    public function __construct(private ProcessPayment $processPayment) {}

    public function __invoke(CreatePaymentRequest $request): JsonResponse
    {
        return PaymentJsonResponse::from($this->processPayment->execute($request->toCommand()));
    }
}
