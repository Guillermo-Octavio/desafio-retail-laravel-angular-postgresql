<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The CHECK constraints repeat the domain invariants so they hold even if the application
 * has a bug (DD-12). The values are written literally because a migration is a snapshot of
 * the schema at this point in time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('merchant_id', 64);
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->string('status', 16);
            $table->string('decline_reason', 32)->nullable();
            $table->string('acquirer_reference', 64)->nullable();
            $table->string('card_brand', 16);
            $table->char('card_last4', 4);
            $table->string('correlation_id', 64);
            $table->timestampsTz(6);

            $table->rawIndex('merchant_id, created_at DESC', 'transactions_merchant_id_created_at_index');
            $table->rawIndex('status, created_at DESC', 'transactions_status_created_at_index');
            $table->rawIndex('created_at DESC', 'transactions_created_at_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE transactions
                ADD CONSTRAINT transactions_status_check
                    CHECK (status IN ('PENDING', 'PROCESSING', 'APPROVED', 'DECLINED', 'FAILED')),
                ADD CONSTRAINT transactions_currency_check
                    CHECK (currency IN ('CLP', 'USD')),
                ADD CONSTRAINT transactions_amount_check
                    CHECK (amount > 0),
                ADD CONSTRAINT transactions_decline_reason_check
                    CHECK ((status IN ('DECLINED', 'FAILED')) = (decline_reason IS NOT NULL)),
                ADD CONSTRAINT transactions_acquirer_reference_check
                    CHECK ((status = 'APPROVED') = (acquirer_reference IS NOT NULL))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
