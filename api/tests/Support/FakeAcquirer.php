<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Answers acquirer calls like the Acquirer Mock does for each test card
 * (requirements analysis, section 7), and never waits for real between retries.
 */
final class FakeAcquirer
{
    /** @var array<string, int> */
    private array $attemptsByTransaction = [];

    public static function install(): self
    {
        $acquirer = new self;
        Sleep::fake();
        Http::fake(['*/acquirer/authorize' => fn (Request $request) => $acquirer->answer($request)]);

        return $acquirer;
    }

    public function calls(): int
    {
        return array_sum($this->attemptsByTransaction);
    }

    private function answer(Request $request): mixed
    {
        $transactionId = (string) $request['transaction_id'];
        $attempt = $this->attemptsByTransaction[$transactionId] = ($this->attemptsByTransaction[$transactionId] ?? 0) + 1;

        return match ($request['card']['number']) {
            '4000000000000002' => Http::response(['status' => 'DECLINED', 'reason' => 'INSUFFICIENT_FUNDS']),
            '4000000000000119' => Http::response(['error' => 'TEMPORARY_UNAVAILABLE'], 503),
            '4000000000000259' => $attempt === 1
                ? Http::response(['error' => 'TEMPORARY_UNAVAILABLE'], 503)
                : Http::response(['status' => 'APPROVED', 'authorization_code' => 'B2C3D4']),
            default => Http::response(['status' => 'APPROVED', 'authorization_code' => 'A1B2C3']),
        };
    }
}
