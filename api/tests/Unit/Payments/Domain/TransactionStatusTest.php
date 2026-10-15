<?php

declare(strict_types=1);

use App\Payments\Domain\TransactionStatus;

it('marks APPROVED, DECLINED and FAILED as final', function () {
    $final = array_filter(TransactionStatus::cases(), fn (TransactionStatus $status) => $status->isFinal());

    expect(array_values($final))->toBe([TransactionStatus::APPROVED, TransactionStatus::DECLINED, TransactionStatus::FAILED]);
});

it('allows only the transitions of the state machine', function () {
    $allowed = [];

    foreach (TransactionStatus::cases() as $from) {
        foreach (TransactionStatus::cases() as $to) {
            if ($from->canTransitionTo($to)) {
                $allowed[] = "{$from->value}->{$to->value}";
            }
        }
    }

    expect($allowed)->toBe([
        'PENDING->PROCESSING',
        'PENDING->DECLINED',
        'PROCESSING->APPROVED',
        'PROCESSING->DECLINED',
        'PROCESSING->FAILED',
    ]);
});
