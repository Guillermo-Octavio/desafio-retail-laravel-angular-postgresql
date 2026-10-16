<?php

use App\AcquirerMock\AcquirerMockServiceProvider;
use App\Payments\Infrastructure\PaymentsServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    PaymentsServiceProvider::class,
    AcquirerMockServiceProvider::class,
];
