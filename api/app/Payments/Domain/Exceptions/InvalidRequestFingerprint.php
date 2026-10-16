<?php

declare(strict_types=1);

namespace App\Payments\Domain\Exceptions;

final class InvalidRequestFingerprint extends PaymentsDomainException
{
    public static function notSha256Hex(): self
    {
        return new self('The request fingerprint must be a lowercase hexadecimal SHA-256 digest.');
    }
}
