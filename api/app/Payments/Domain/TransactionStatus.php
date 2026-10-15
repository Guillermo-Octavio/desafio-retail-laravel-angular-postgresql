<?php

declare(strict_types=1);

namespace App\Payments\Domain;

enum TransactionStatus: string
{
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case APPROVED = 'APPROVED';
    case DECLINED = 'DECLINED';
    case FAILED = 'FAILED';

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /**
     * @return list<self>
     */
    private function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING => [self::PROCESSING, self::DECLINED],
            self::PROCESSING => [self::APPROVED, self::DECLINED, self::FAILED],
            self::APPROVED, self::DECLINED, self::FAILED => [],
        };
    }
}
