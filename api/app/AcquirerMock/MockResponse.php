<?php

declare(strict_types=1);

namespace App\AcquirerMock;

final readonly class MockResponse
{
    private const int OK = 200;

    private const int SERVICE_UNAVAILABLE = 503;

    /**
     * @param  array<string, string>  $body
     */
    private function __construct(
        public int $statusCode,
        public array $body,
    ) {}

    public static function approved(string $authorizationCode): self
    {
        return new self(self::OK, ['status' => 'APPROVED', 'authorization_code' => $authorizationCode]);
    }

    public static function declined(string $reason): self
    {
        return new self(self::OK, ['status' => 'DECLINED', 'reason' => $reason]);
    }

    public static function temporarilyUnavailable(): self
    {
        return new self(self::SERVICE_UNAVAILABLE, ['error' => 'TEMPORARY_UNAVAILABLE']);
    }
}
