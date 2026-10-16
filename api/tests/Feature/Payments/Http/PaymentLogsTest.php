<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\CapturedLogs;
use Tests\Support\FakeAcquirer;
use Tests\Support\PaymentRows;

beforeEach(function () {
    FakeAcquirer::install();
    $this->logs = CapturedLogs::install();
    $this->pay = fn (array $overrides = [], string $key = 'key-001') => $this->postJson(
        '/api/payments',
        PaymentRows::paymentBody($overrides),
        ['Idempotency-Key' => $key, 'X-Correlation-Id' => 'corr-logs'],
    );
});

it('logs the flow of an approved payment in order', function () {
    ($this->pay)();

    expect($this->logs->messages())->toBe(['payment.received', 'transaction.created', 'acquirer.attempt', 'transaction.finalized']);
});

it('writes every line as JSON with the correlation id, and the transaction id once it exists', function () {
    $transactionId = ($this->pay)()->json('transaction_id');

    $lines = $this->logs->lines();

    expect(array_unique(array_map(fn (array $line) => $line['extra']['correlation_id'] ?? null, $lines)))->toBe(['corr-logs'])
        ->and($lines[0]['extra'])->not->toHaveKey('transaction_id')
        ->and(array_unique(array_map(fn (array $line) => $line['extra']['transaction_id'] ?? null, array_slice($lines, 1))))->toBe([$transactionId]);
});

it('records the result of each acquirer attempt, warning on temporary errors', function () {
    ($this->pay)(['card.number' => '4000000000000259']);

    $attempts = array_values(array_filter($this->logs->lines(), fn (array $line) => $line['message'] === 'acquirer.attempt'));

    expect(array_map(fn (array $line) => [$line['level_name'], $line['context']['attempt'], $line['context']['outcome'], $line['context']['error_code'] ?? null], $attempts))
        ->toBe([['WARNING', 1, 'TEMPORARY_ERROR', 'HTTP_503'], ['INFO', 2, 'APPROVED', null]])
        ->and($attempts[0]['context'])->toHaveKey('duration_ms');
});

it('logs a decline by a business rule with its reason', function () {
    ($this->pay)(['amount' => 5000001]);

    expect($this->logs->messages())->toBe(['payment.received', 'transaction.created', 'transaction.declined_by_rule', 'transaction.finalized'])
        ->and($this->logs->first('transaction.declined_by_rule')['context'])->toBe(['reason' => 'AMOUNT_LIMIT_EXCEEDED'])
        ->and($this->logs->first('transaction.finalized')['context'])->toBe(['status' => 'DECLINED', 'decline_reason' => 'AMOUNT_LIMIT_EXCEEDED']);
});

it('logs replays and idempotency conflicts, including a key stuck in progress', function () {
    ($this->pay)();
    ($this->pay)();
    ($this->pay)(['amount' => 20000]);
    DB::table('idempotency_keys')->insert(PaymentRows::inProgressKeyForBaseBody('key-stuck'));
    ($this->pay)([], 'key-stuck');

    expect($this->logs->first('idempotency.replayed')['level_name'])->toBe('INFO')
        ->and(array_map(
            fn (array $line) => [$line['level_name'], $line['context']['reason']],
            array_values(array_filter($this->logs->lines(), fn (array $line) => $line['message'] === 'idempotency.conflict')),
        ))->toBe([['WARNING', 'reused'], ['WARNING', 'in_progress']]);
});

it('logs which fields failed validation without their values', function () {
    ($this->pay)(['amount' => 0, 'card.number' => '4111']);

    $line = $this->logs->first('payment.validation_failed');

    expect($line['context'])->toBe(['fields' => ['amount', 'card.number']])
        ->and($this->logs->raw())->not->toContain('"4111"');
});

it('logs an unexpected failure as an error', function () {
    Route::middleware('api')->get('api/test-logs/crash', fn () => throw new RuntimeException('boom'));

    $this->getJson('/api/test-logs/crash', ['X-Correlation-Id' => 'corr-crash'])->assertStatus(500);

    $line = $this->logs->first('payment.unexpected_error');
    expect($line['level_name'])->toBe('ERROR')
        ->and($line['extra']['correlation_id'])->toBe('corr-crash');
});

it('never writes the card number or the CVV in any log line', function () {
    ($this->pay)(['card.cvv' => '987']);
    ($this->pay)(['card.number' => '4000000000000259', 'card.cvv' => '987'], 'key-002');
    ($this->pay)(['card.number' => '4111111111111112', 'card.cvv' => '987'], 'key-003');

    expect($this->logs->raw())
        ->not->toContain('4111111111111111')
        ->not->toContain('4000000000000259')
        ->not->toContain('4111111111111112')
        ->not->toContain('"987"')
        ->not->toContain('cvv');
});
