<?php

declare(strict_types=1);

namespace App\Payments\Domain;

enum DeclineReason: string
{
    case AMOUNT_LIMIT_EXCEEDED = 'AMOUNT_LIMIT_EXCEEDED';
    case INVALID_CARD_NUMBER = 'INVALID_CARD_NUMBER';
    case CARD_EXPIRED = 'CARD_EXPIRED';
    case INSUFFICIENT_FUNDS = 'INSUFFICIENT_FUNDS';
    case ACQUIRER_UNAVAILABLE = 'ACQUIRER_UNAVAILABLE';
    case ACQUIRER_ERROR = 'ACQUIRER_ERROR';
}
