<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Payments\Domain\Card\CardBrand;
use App\Payments\Domain\Card\CardDetails;
use App\Payments\Domain\Card\CardExpiry;
use App\Payments\Domain\Card\CardNumber;
use App\Payments\Domain\Card\Cvv;
use App\Payments\Domain\Card\MaskedCard;
use App\Payments\Domain\CorrelationId;
use App\Payments\Domain\Currency;
use App\Payments\Domain\Idempotency\RequestFingerprint;
use App\Payments\Domain\MerchantId;
use App\Payments\Domain\Money;
use App\Payments\Domain\PaymentRequest;
use App\Payments\Domain\Transaction;
use App\Payments\Domain\TransactionEvent;
use App\Payments\Domain\TransactionId;
use DateTimeImmutable;
use DateTimeZone;
use Random\Engine\Mt19937;
use Random\Randomizer;

final class PaymentFixtures
{
    public const string VALID_CARD = '4111111111111111';

    public const string LUHN_INVALID_CARD = '4111111111111112';

    public static function seededRandomizer(): Randomizer
    {
        return new Randomizer(new Mt19937(42));
    }

    public static function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable("2026-10-15 {$time}", new DateTimeZone('UTC'));
    }

    public static function paymentRequest(
        int $amount = 15000,
        Currency $currency = Currency::CLP,
        string $cardNumber = self::VALID_CARD,
        int $expiryMonth = 12,
        int $expiryYear = 2030,
    ): PaymentRequest {
        return new PaymentRequest(
            new MerchantId('merchant-001'),
            new Money($amount, $currency),
            new CardDetails(new CardNumber($cardNumber), new CardExpiry($expiryMonth, $expiryYear), new Cvv('123')),
        );
    }

    public static function fingerprint(string $hexCharacter): RequestFingerprint
    {
        return new RequestFingerprint(str_repeat($hexCharacter, 64));
    }

    public static function newTransaction(): Transaction
    {
        return Transaction::create(
            new TransactionId('0199f0c2-6a1e-7c3a-9b2f-5d1e8a4c7b10'),
            new MerchantId('merchant-001'),
            new Money(15000, Currency::CLP),
            new MaskedCard(CardBrand::VISA, '1111'),
            new CorrelationId('corr-123'),
            self::at('12:00:00.000001'),
        );
    }

    public static function processingTransaction(): Transaction
    {
        $transaction = self::newTransaction();
        $transaction->startProcessing(self::at('12:00:01'));
        $transaction->clearRecordedEvents();

        return $transaction;
    }

    /**
     * @param  list<TransactionEvent>  $events
     * @return list<array<string, mixed>>
     */
    public static function describeEvents(array $events): array
    {
        return array_map(fn (TransactionEvent $event) => [
            'type' => $event->type,
            'from' => $event->fromStatus,
            'to' => $event->toStatus,
            'reason' => $event->reason,
            'metadata' => $event->metadata,
        ], $events);
    }
}
