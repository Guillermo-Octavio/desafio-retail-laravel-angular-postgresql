<?php

declare(strict_types=1);

namespace App\Payments\Domain;

enum EventType: string
{
    case STATUS_CHANGED = 'STATUS_CHANGED';
    case ACQUIRER_ATTEMPT = 'ACQUIRER_ATTEMPT';
}
