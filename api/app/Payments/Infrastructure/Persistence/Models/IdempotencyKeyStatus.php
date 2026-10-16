<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Persistence\Models;

enum IdempotencyKeyStatus: string
{
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';
}
