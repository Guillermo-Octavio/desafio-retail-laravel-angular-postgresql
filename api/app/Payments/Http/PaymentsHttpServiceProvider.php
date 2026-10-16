<?php

declare(strict_types=1);

namespace App\Payments\Http;

use App\Payments\Application\Ports\PaymentResponsePresenter;
use App\Payments\Http\Resources\TransactionResourcePresenter;
use Illuminate\Support\ServiceProvider;

// Lives in Http because Infrastructure must not depend on the Http layer (DA-02).
final class PaymentsHttpServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        PaymentResponsePresenter::class => TransactionResourcePresenter::class,
    ];
}
