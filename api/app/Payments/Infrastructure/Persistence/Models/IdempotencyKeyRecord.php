<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Row of the idempotency_keys table.
 */
final class IdempotencyKeyRecord extends Model
{
    protected $table = 'idempotency_keys';

    // The store writes created_at and updated_at from the Clock port.
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => IdempotencyKeyStatus::class,
            'response_status' => 'integer',
            'response_body' => 'array',
        ];
    }
}
