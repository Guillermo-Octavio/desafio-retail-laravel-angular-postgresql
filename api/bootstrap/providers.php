<?php

use App\AcquirerMock\AcquirerMockServiceProvider;
use App\Payments\Http\PaymentsHttpServiceProvider;
use App\Payments\Infrastructure\PaymentsServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    PaymentsServiceProvider::class,
    PaymentsHttpServiceProvider::class,
    AcquirerMockServiceProvider::class,
];
