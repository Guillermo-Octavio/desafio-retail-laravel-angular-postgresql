<?php

declare(strict_types=1);

namespace App\Payments\Application;

use App\Payments\Application\Exceptions\IdempotencyKeyInProgress;
use App\Payments\Application\Exceptions\IdempotencyKeyReused;
use App\Payments\Application\Ports\PaymentResponsePresenter;
use App\Payments\Application\Ports\RequestFingerprinter;
use App\Payments\Domain\Acquirer\AcquirerRequest;
use App\Payments\Domain\Idempotency\IdempotencyReservation;
use App\Payments\Domain\Idempotency\ReservationOutcome;
use App\Payments\Domain\PaymentRequest;
use App\Payments\Domain\Ports\Clock;
use App\Payments\Domain\Ports\IdempotencyStore;
use App\Payments\Domain\Ports\TransactionIdGenerator;
use App\Payments\Domain\Ports\TransactionRepository;
use App\Payments\Domain\Rules\BusinessRules;
use App\Payments\Domain\Transaction;

/**
 * Processes a payment end to end (architecture section 4). Each step is committed on its own
 * and no database transaction stays open while the acquirer is called (DA-04).
 */
final readonly class ProcessPayment
{
    public function __construct(
        private IdempotencyStore $idempotencyStore,
        private RequestFingerprinter $fingerprinter,
        private TransactionRepository $transactions,
        private TransactionIdGenerator $transactionIds,
        private Clock $clock,
        private BusinessRules $rules,
        private AcquirerAuthorization $acquirerAuthorization,
        private PaymentResponsePresenter $presenter,
    ) {}

    /**
     * @throws IdempotencyKeyReused when the key was used with different content.
     * @throws IdempotencyKeyInProgress when the first request with the key has not finished.
     */
    public function execute(ProcessPaymentCommand $command): PaymentResponse
    {
        $reservation = $this->idempotencyStore->reserve(
            $command->merchantId,
            $command->idempotencyKey,
            $this->fingerprinter->fingerprint($command),
        );

        return match ($reservation->outcome) {
            ReservationOutcome::RESERVED => $this->process($command),
            ReservationOutcome::REPLAY => $this->replay($reservation),
            ReservationOutcome::FINGERPRINT_MISMATCH => throw IdempotencyKeyReused::withDifferentContent(),
            ReservationOutcome::IN_PROGRESS => throw IdempotencyKeyInProgress::create(),
        };
    }

    private function process(ProcessPaymentCommand $command): PaymentResponse
    {
        $transaction = Transaction::create(
            $this->transactionIds->generate(),
            $command->merchantId,
            $command->money,
            $command->card->number->masked(),
            $command->correlationId,
            $this->clock->now(),
        );
        $this->transactions->save($transaction);
        $this->idempotencyStore->attachTransaction($command->merchantId, $command->idempotencyKey, $transaction->id());

        $this->resolve($transaction, $command);

        $response = $this->presenter->present($transaction);
        $this->idempotencyStore->complete($command->merchantId, $command->idempotencyKey, $response);

        return PaymentResponse::original($response);
    }

    private function resolve(Transaction $transaction, ProcessPaymentCommand $command): void
    {
        $violation = $this->rules->firstViolation(new PaymentRequest($command->merchantId, $command->money, $command->card));

        if ($violation !== null) {
            $transaction->decline($violation, $this->clock->now());
            $this->transactions->save($transaction);

            return;
        }

        $transaction->startProcessing($this->clock->now());
        $this->transactions->save($transaction);

        $this->acquirerAuthorization->authorize($transaction, new AcquirerRequest(
            $transaction->id(),
            $command->merchantId,
            $command->money,
            $command->card,
            $command->correlationId,
        ));
    }

    private function replay(IdempotencyReservation $reservation): PaymentResponse
    {
        return PaymentResponse::replayed($reservation->storedResponse ?? throw IdempotencyKeyInProgress::create());
    }
}
