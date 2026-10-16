<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure;

use App\Payments\Domain\Ports\Clock;
use App\Payments\Domain\Ports\IdempotencyStore;
use App\Payments\Domain\Ports\TransactionIdGenerator;
use App\Payments\Domain\Ports\TransactionRepository;
use App\Payments\Infrastructure\Persistence\EloquentTransactionRepository;
use App\Payments\Infrastructure\Persistence\PostgresIdempotencyStore;
use Illuminate\Support\ServiceProvider;

final class PaymentsServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        Clock::class => SystemClock::class,
        TransactionIdGenerator::class => UuidV7TransactionIdGenerator::class,
        TransactionRepository::class => EloquentTransactionRepository::class,
        IdempotencyStore::class => PostgresIdempotencyStore::class,
    ];
}
