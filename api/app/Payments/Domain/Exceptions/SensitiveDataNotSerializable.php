<?php

declare(strict_types=1);

namespace App\Payments\Domain\Exceptions;

final class SensitiveDataNotSerializable extends PaymentsDomainException
{
    public static function of(string $type): self
    {
        return new self("{$type} holds sensitive card data and cannot be serialized.");
    }
}
