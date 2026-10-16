<?php

declare(strict_types=1);

namespace Tests\Support;

final class AcquirerMockRequests
{
    /**
     * @return array<string, mixed>
     */
    public static function authorization(
        string $cardNumber = '4111111111111111',
        string $transactionId = '0199f0c2-6a1e-7c3a-9b2f-5d1e8a4c7b10',
    ): array {
        return [
            'transaction_id' => $transactionId,
            'merchant_id' => 'merchant-001',
            'amount' => 15000,
            'currency' => 'CLP',
            'card' => ['number' => $cardNumber, 'expiry_month' => 12, 'expiry_year' => 2030, 'cvv' => '123'],
        ];
    }
}
