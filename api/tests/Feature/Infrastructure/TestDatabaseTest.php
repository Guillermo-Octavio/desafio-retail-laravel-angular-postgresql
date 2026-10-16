<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('runs against a dedicated PostgreSQL database, never the development one', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(DB::connection()->getDatabaseName())->toBe('payments_test');
});
