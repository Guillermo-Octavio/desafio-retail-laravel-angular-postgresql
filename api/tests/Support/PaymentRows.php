<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Raw table rows used to check the database constraints directly, bypassing the domain.
 */
final class PaymentRows
{
    public const string TRANSACTION_ID = '0199f0c2-6a1e-7c3a-9b2f-5d1e8a4c7b10';

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function transaction(array $overrides = []): array
    {
        return [
            'id' => self::TRANSACTION_ID,
            'merchant_id' => 'merchant-001',
            'amount' => 15000,
            'currency' => 'CLP',
            'status' => 'PENDING',
            'decline_reason' => null,
            'acquirer_reference' => null,
            'card_brand' => 'VISA',
            'card_last4' => '1111',
            'correlation_id' => 'corr-123',
            'created_at' => '2026-10-15 12:00:00+00',
            'updated_at' => '2026-10-15 12:00:00+00',
            ...$overrides,
        ];
    }
}
