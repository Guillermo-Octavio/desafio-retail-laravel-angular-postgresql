<?php

use App\Payments\Http\Controllers\CreatePaymentController;
use App\Payments\Http\Middleware\RequireIdempotencyKey;
use Illuminate\Support\Facades\Route;

Route::post('/payments', CreatePaymentController::class)->middleware(RequireIdempotencyKey::class);
