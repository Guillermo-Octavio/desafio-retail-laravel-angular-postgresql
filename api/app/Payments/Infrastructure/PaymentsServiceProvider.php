<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure;

use App\Payments\Application\Ports\MonotonicTimer;
use App\Payments\Application\Ports\RequestFingerprinter;
use App\Payments\Application\Ports\Sleeper;
use App\Payments\Application\Ports\TransactionQueries;
use App\Payments\Application\RetryPolicy;
use App\Payments\Domain\Ports\AcquirerGateway;
use App\Payments\Domain\Ports\Clock;
use App\Payments\Domain\Ports\IdempotencyStore;
use App\Payments\Domain\Ports\TransactionIdGenerator;
use App\Payments\Domain\Ports\TransactionRepository;
use App\Payments\Domain\Rules\AmountLimits;
use App\Payments\Domain\Rules\BusinessRules;
use App\Payments\Infrastructure\Acquirer\HttpAcquirerGateway;
use App\Payments\Infrastructure\Persistence\EloquentTransactionQueries;
use App\Payments\Infrastructure\Persistence\EloquentTransactionRepository;
use App\Payments\Infrastructure\Persistence\PostgresIdempotencyStore;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use Random\Randomizer;

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
        TransactionQueries::class => EloquentTransactionQueries::class,
        Sleeper::class => SleepSleeper::class,
        MonotonicTimer::class => HrtimeMonotonicTimer::class,
    ];

    public function register(): void
    {
        $this->app->singleton(RetryPolicy::class, fn () => new RetryPolicy(
            (int) config('payments.acquirer.max_attempts'),
            (int) config('payments.acquirer.initial_backoff_ms'),
            (int) config('payments.acquirer.max_jitter_ms'),
            new Randomizer,
        ));

        $this->app->singleton(BusinessRules::class, fn ($app) => BusinessRules::standard(
            $app->make(Clock::class),
            new AmountLimits(config('payments.amount_limits')),
        ));

        $this->app->bind(AcquirerGateway::class, fn ($app) => new HttpAcquirerGateway(
            $app->make(HttpFactory::class),
            (string) config('payments.acquirer.base_url'),
            (int) config('payments.acquirer.connect_timeout_seconds'),
            (int) config('payments.acquirer.timeout_seconds'),
        ));

        $this->app->singleton(RequestFingerprinter::class, fn () => new HmacRequestFingerprinter(
            (string) config('app.key'),
        ));
    }
}
