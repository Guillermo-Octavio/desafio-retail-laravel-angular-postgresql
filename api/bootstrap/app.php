<?php

use App\Payments\Http\PaymentsErrorRendering;
use App\Shared\Http\ApiErrorRendering;
use App\Shared\Http\Middleware\AssignCorrelationId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignCorrelationId::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => ApiErrorRendering::isApiRequest($request) || $request->expectsJson(),
        );

        // Module errors first: the shared rendering ends with a catch-all for internal errors.
        PaymentsErrorRendering::register($exceptions);
        ApiErrorRendering::register($exceptions);
    })->create();
