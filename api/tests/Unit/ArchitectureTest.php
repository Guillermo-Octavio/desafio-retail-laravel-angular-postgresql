<?php

declare(strict_types=1);

$frameworkHelpers = ['now', 'today', 'config', 'app', 'request', 'response', 'env', 'event', 'logger', 'cache', 'session', 'dispatch'];

arch('the payments domain is plain PHP')
    ->expect('App\Payments\Domain')
    ->not->toUse(['Illuminate', 'Laravel', 'App\Payments\Application', 'App\Payments\Infrastructure', 'App\Payments\Http', ...$frameworkHelpers]);

arch('the payments application layer does not use Eloquent, HTTP or facades')
    ->expect('App\Payments\Application')
    ->not->toUse(['Illuminate\Database', 'Illuminate\Http', 'Illuminate\Support\Facades', 'App\Payments\Infrastructure', 'App\Payments\Http']);

arch('the payments infrastructure does not depend on the HTTP layer')
    ->expect('App\Payments\Infrastructure')
    ->not->toUse('App\Payments\Http');

arch('the payments HTTP layer does not depend on the infrastructure')
    ->expect('App\Payments\Http')
    ->not->toUse(['App\Payments\Infrastructure', 'Illuminate\Database']);

arch('the acquirer mock does not depend on the payments module')
    ->expect('App\AcquirerMock')
    ->not->toUse('App\Payments');

arch('the payments, acquirer mock and shared modules use strict types')
    ->expect(['App\Payments', 'App\AcquirerMock', 'App\Shared'])
    ->toUseStrictTypes();

arch('no debugging statements are left in the application')
    ->expect(['dd', 'dump', 'var_dump', 'ray', 'print_r'])
    ->not->toBeUsed();
