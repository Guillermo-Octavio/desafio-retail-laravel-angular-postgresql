<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('merchant_id', 64);
            $table->string('idempotency_key', 255);
            $table->char('request_fingerprint', 64);
            $table->string('status', 16);
            $table->foreignUuid('transaction_id')->nullable()->constrained('transactions');
            $table->smallInteger('response_status')->nullable();
            $table->jsonb('response_body')->nullable();
            // timestampsTz() would create nullable columns; the data model requires them.
            $table->timestampTz('created_at', 6);
            $table->timestampTz('updated_at', 6);

            // Resolves concurrent requests with the same key: only one insert can succeed (DD-09).
            $table->unique(['merchant_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
