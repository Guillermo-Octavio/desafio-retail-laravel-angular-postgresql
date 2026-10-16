<?php

declare(strict_types=1);

use App\Payments\Application\Ports\RequestFingerprinter;
use App\Payments\Domain\Ports\Clock;
use App\Payments\Domain\Ports\IdempotencyStore;
use App\Payments\Domain\Ports\TransactionIdGenerator;
use App\Payments\Domain\Ports\TransactionRepository;
use App\Payments\Infrastructure\HmacRequestFingerprinter;
use App\Payments\Infrastructure\Persistence\EloquentTransactionRepository;
use App\Payments\Infrastructure\Persistence\PostgresIdempotencyStore;
use App\Payments\Infrastructure\SystemClock;
use App\Payments\Infrastructure\UuidV7TransactionIdGenerator;
use Illuminate\Support\Carbon;

it('binds each payments port to its adapter', function (string $port, string $adapter) {
    expect(app($port))->toBeInstanceOf($adapter);
})->with([
    'clock' => [Clock::class, SystemClock::class],
    'transaction id generator' => [TransactionIdGenerator::class, UuidV7TransactionIdGenerator::class],
    'transaction repository' => [TransactionRepository::class, EloquentTransactionRepository::class],
    'idempotency store' => [IdempotencyStore::class, PostgresIdempotencyStore::class],
    'request fingerprinter' => [RequestFingerprinter::class, HmacRequestFingerprinter::class],
]);

it('tells the current time in UTC with microseconds', function () {
    Carbon::setTestNow('2026-10-15 09:00:00.123456-03:00');

    $now = app(Clock::class)->now();

    expect($now->format('Y-m-d H:i:s.u'))->toBe('2026-10-15 12:00:00.123456')
        ->and($now->getTimezone()->getName())->toBe('UTC');
});

it('generates distinct time-ordered UUID v7 transaction ids', function () {
    $generator = app(TransactionIdGenerator::class);

    Carbon::setTestNow('2026-10-15 12:00:00.001');
    $first = $generator->generate();
    Carbon::setTestNow('2026-10-15 12:00:00.002');
    $second = $generator->generate();

    expect($first->equals($second))->toBeFalse()
        ->and(strcmp($first->value, $second->value))->toBeLessThan(0);
});
