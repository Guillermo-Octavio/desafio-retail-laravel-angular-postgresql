<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Row of the transactions table. The domain never sees it: the repository maps it to and
 * from the Transaction aggregate.
 */
final class TransactionRecord extends Model
{
    protected $table = 'transactions';

    protected $keyType = 'string';

    public $incrementing = false;

    // The repository writes created_at and updated_at from the aggregate and the Clock port.
    public $timestamps = false;

    protected $guarded = [];
}
