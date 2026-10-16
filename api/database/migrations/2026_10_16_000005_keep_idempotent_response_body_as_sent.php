<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * jsonb reorders the keys of the stored response, so a replay would not return exactly the
 * original body (HU-06). json keeps the text as it was written.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE idempotency_keys ALTER COLUMN response_body TYPE json USING response_body::json');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE idempotency_keys ALTER COLUMN response_body TYPE jsonb USING response_body::jsonb');
    }
};
