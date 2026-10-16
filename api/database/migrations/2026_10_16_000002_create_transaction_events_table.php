<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Events are append-only in the database too, so traceability does not depend only on the
 * application code (DD-11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('transaction_id')->constrained('transactions');
            $table->string('type', 32);
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16)->nullable();
            $table->string('reason', 32)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->string('correlation_id', 64);
            $table->timestampTz('occurred_at', 6);

            $table->index(['transaction_id', 'id']);
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION reject_transaction_event_changes() RETURNS trigger
                LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'transaction_events is append-only: % is not allowed', TG_OP;
            END;
            $$;

            CREATE TRIGGER transaction_events_append_only
                BEFORE UPDATE OR DELETE ON transaction_events
                FOR EACH ROW EXECUTE FUNCTION reject_transaction_event_changes();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_events');
        DB::unprepared('DROP FUNCTION IF EXISTS reject_transaction_event_changes()');
    }
};
