<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure;

use App\Payments\Application\Ports\RequestFingerprinter;
use App\Payments\Application\ProcessPaymentCommand;
use App\Payments\Domain\Idempotency\RequestFingerprint;
use App\Payments\Infrastructure\Exceptions\MissingFingerprintSecret;
use SensitiveParameter;

/**
 * HMAC-SHA256 instead of a plain hash: the content includes the card number, and a plain hash
 * of a card number can be reversed by brute force (DD-10).
 */
final readonly class HmacRequestFingerprinter implements RequestFingerprinter
{
    private const string ALGORITHM = 'sha256';

    public function __construct(#[SensitiveParameter] private string $secret)
    {
        if ($secret === '') {
            throw MissingFingerprintSecret::create();
        }
    }

    public function fingerprint(ProcessPaymentCommand $command): RequestFingerprint
    {
        $content = json_encode($this->normalizedContent($command), JSON_THROW_ON_ERROR);

        return new RequestFingerprint(hash_hmac(self::ALGORITHM, $content, $this->secret));
    }

    /**
     * Fixed key order so the same content always produces the same JSON. The CVV is left out:
     * sensitive authentication data must not be kept after authorization in any form, not even
     * as a keyed hash (PCI DSS).
     *
     * @return array<string, mixed>
     */
    private function normalizedContent(ProcessPaymentCommand $command): array
    {
        return [
            'merchant_id' => $command->merchantId->value,
            'amount' => $command->money->amount,
            'currency' => $command->money->currency->value,
            'card' => [
                'number' => $command->card->number->reveal(),
                'holder_name' => $command->cardHolderName,
                'expiry_month' => $command->card->expiry->month,
                'expiry_year' => $command->card->expiry->year,
            ],
        ];
    }
}
