<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Payments\Application\Ports\RequestFingerprinter;

/**
 * Raw data for feature tests: table rows that bypass the domain to check the database
 * constraints, the base payment request body, and idempotency rows for that body.
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function event(array $overrides = []): array
    {
        return [
            'transaction_id' => self::TRANSACTION_ID,
            'sequence' => 1,
            'type' => 'STATUS_CHANGED',
            'from_status' => null,
            'to_status' => 'PENDING',
            'reason' => null,
            'metadata' => null,
            'correlation_id' => 'corr-123',
            'occurred_at' => '2026-10-15 12:00:00.123456+00',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function idempotencyKey(array $overrides = []): array
    {
        return [
            'merchant_id' => 'merchant-001',
            'idempotency_key' => 'key-1',
            'request_fingerprint' => str_repeat('a', 64),
            'status' => 'IN_PROGRESS',
            'transaction_id' => null,
            'response_status' => null,
            'response_body' => null,
            'created_at' => '2026-10-15 12:00:00+00',
            'updated_at' => '2026-10-15 12:00:00+00',
            ...$overrides,
        ];
    }

    /**
     * The base payment request body of the user stories.
     *
     * @param  array<string, mixed>  $overrides  dot-notation keys, such as "card.number"
     * @return array<string, mixed>
     */
    public static function paymentBody(array $overrides = []): array
    {
        $body = [
            'merchant_id' => 'merchant-001',
            'amount' => 15000,
            'currency' => 'CLP',
            'card' => [
                'number' => '4111111111111111',
                'holder_name' => 'Ada Lovelace',
                'expiry_month' => 12,
                'expiry_year' => 2030,
                'cvv' => '123',
            ],
        ];

        foreach ($overrides as $key => $value) {
            data_set($body, $key, $value);
        }

        return $body;
    }

    /**
     * A key still being processed for the base payment body, with its real fingerprint.
     *
     * @return array<string, mixed>
     */
    public static function inProgressKeyForBaseBody(string $idempotencyKey): array
    {
        $fingerprint = app(RequestFingerprinter::class)->fingerprint(PaymentFixtures::command(idempotencyKey: $idempotencyKey));

        return self::idempotencyKey(['idempotency_key' => $idempotencyKey, 'request_fingerprint' => $fingerprint->value]);
    }
}
