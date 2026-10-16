<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each event carries its number within the transaction, so saving the same event again is
 * ignored instead of duplicating an append-only row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_events', function (Blueprint $table) {
            $table->integer('sequence');
            $table->unique(['transaction_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::table('transaction_events', function (Blueprint $table) {
            $table->dropUnique(['transaction_id', 'sequence']);
            $table->dropColumn('sequence');
        });
    }
};
