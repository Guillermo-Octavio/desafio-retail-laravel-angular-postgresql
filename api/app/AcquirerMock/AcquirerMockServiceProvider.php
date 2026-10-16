<?php

declare(strict_types=1);

namespace App\AcquirerMock;

use App\AcquirerMock\Http\AuthorizeController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class AcquirerMockServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! config('acquirer_mock.enabled')) {
            return;
        }

        Route::middleware('api')->post('/acquirer/authorize', AuthorizeController::class);
    }
}
