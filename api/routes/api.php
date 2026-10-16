<?php

use App\Payments\Http\Controllers\CreatePaymentController;
use App\Payments\Http\Controllers\GetPaymentController;
use App\Payments\Http\Controllers\ListPaymentsController;
use App\Payments\Http\Middleware\RequireIdempotencyKey;
use Illuminate\Support\Facades\Route;

Route::post('/payments', CreatePaymentController::class)->middleware(RequireIdempotencyKey::class);
Route::get('/payments/{transactionId}', GetPaymentController::class);
Route::get('/payments', ListPaymentsController::class);
