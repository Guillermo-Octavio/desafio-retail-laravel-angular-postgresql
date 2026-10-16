<?php

declare(strict_types=1);

namespace App\AcquirerMock\Http;

use App\AcquirerMock\AcquirerSimulator;
use Illuminate\Http\JsonResponse;

final readonly class AuthorizeController
{
    public function __construct(private AcquirerSimulator $simulator) {}

    public function __invoke(AuthorizeRequest $request): JsonResponse
    {
        $response = $this->simulator->authorize(
            $request->string('card.number')->value(),
            $request->string('transaction_id')->value(),
        );

        return new JsonResponse($response->body, $response->statusCode);
    }
}
