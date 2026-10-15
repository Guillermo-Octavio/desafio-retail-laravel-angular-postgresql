<?php

declare(strict_types=1);

namespace App\Payments\Domain;

enum AcquirerOutcome: string
{
    case APPROVED = 'APPROVED';
    case DECLINED = 'DECLINED';
    case TEMPORARY_ERROR = 'TEMPORARY_ERROR';
}
