<?php

declare(strict_types=1);

namespace App\Payments\Application;

use App\Payments\Application\Exceptions\IdempotencyKeyInProgress;
use App\Payments\Application\Exceptions\IdempotencyKeyReused;
use App\Payments\Application\Ports\LogContext;
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
use Psr\Log\LoggerInterface;

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
        private LoggerInterface $logger,
        private LogContext $logContext,
    ) {}

    /**
     * @throws IdempotencyKeyReused when the key was used with different content.
     * @throws IdempotencyKeyInProgress when the first request with the key has not finished.
     */
    public function execute(ProcessPaymentCommand $command): PaymentResponse
    {
        $this->logger->info('payment.received', [
            'merchant_id' => $command->merchantId->value,
            'amount' => $command->money->amount,
            'currency' => $command->money->currency->value,
        ]);

        $reservation = $this->idempotencyStore->reserve(
            $command->merchantId,
            $command->idempotencyKey,
            $this->fingerprinter->fingerprint($command),
        );

        return match ($reservation->outcome) {
            ReservationOutcome::RESERVED => $this->process($command),
            ReservationOutcome::REPLAY => $this->replay($reservation),
            ReservationOutcome::FINGERPRINT_MISMATCH => $this->conflict('reused', IdempotencyKeyReused::withDifferentContent()),
            ReservationOutcome::IN_PROGRESS => $this->conflict('in_progress', IdempotencyKeyInProgress::create()),
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
        $this->logContext->addTransactionId($transaction->id());
        $this->logger->info('transaction.created');
        $this->idempotencyStore->attachTransaction($command->merchantId, $command->idempotencyKey, $transaction->id());

        $this->resolve($transaction, $command);
        $this->logger->info('transaction.finalized', [
            'status' => $transaction->status()->value,
            'decline_reason' => $transaction->declineReason()?->value,
        ]);

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
            $this->logger->info('transaction.declined_by_rule', ['reason' => $violation->value]);

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

    /**
     * A key stuck in progress shows up here as a warning, so it can be noticed and repaired.
     */
    private function conflict(string $reason, IdempotencyKeyReused|IdempotencyKeyInProgress $error): never
    {
        $this->logger->warning('idempotency.conflict', ['reason' => $reason]);

        throw $error;
    }

    private function replay(IdempotencyReservation $reservation): PaymentResponse
    {
        $this->logger->info('idempotency.replayed');

        return PaymentResponse::replayed($reservation->storedResponse ?? throw IdempotencyKeyInProgress::create());
    }
}
