<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Row of the append-only transaction_events table.
 */
final class TransactionEventRecord extends Model
{
    protected $table = 'transaction_events';

    public $timestamps = false;

    protected $guarded = [];
}
