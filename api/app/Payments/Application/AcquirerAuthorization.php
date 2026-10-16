<?php

declare(strict_types=1);

namespace App\Payments\Application;

use App\Payments\Application\Ports\MonotonicTimer;
use App\Payments\Application\Ports\Sleeper;
use App\Payments\Domain\Acquirer\AcquirerRequest;
use App\Payments\Domain\AcquirerOutcome;
use App\Payments\Domain\DeclineReason;
use App\Payments\Domain\Exceptions\AcquirerIntegrationFailed;
use App\Payments\Domain\Exceptions\AcquirerTemporarilyUnavailable;
use App\Payments\Domain\Ports\AcquirerGateway;
use App\Payments\Domain\Ports\Clock;
use App\Payments\Domain\Ports\TransactionRepository;
use App\Payments\Domain\Transaction;

/**
 * Calls the acquirer for a PROCESSING transaction until it gets a final answer. The retry loop
 * lives here, not in the HTTP client, so every attempt is recorded as an event (DA-05).
 */
final readonly class AcquirerAuthorization
{
    private const int NANOSECONDS_PER_MILLISECOND = 1_000_000;

    public function __construct(
        private AcquirerGateway $gateway,
        private RetryPolicy $retryPolicy,
        private Sleeper $sleeper,
        private Clock $clock,
        private MonotonicTimer $timer,
        private TransactionRepository $transactions,
    ) {}

    public function authorize(Transaction $transaction, AcquirerRequest $request): void
    {
        for ($attempt = 1; $this->retryPolicy->allowsAttempt($attempt); $attempt++) {
            if ($this->attempt($transaction, $request, $attempt)) {
                return;
            }
        }

        $transaction->fail(DeclineReason::ACQUIRER_UNAVAILABLE, $this->clock->now());
        $this->transactions->save($transaction);
    }

    /**
     * Returns true when the transaction reached a final state.
     */
    private function attempt(Transaction $transaction, AcquirerRequest $request, int $attempt): bool
    {
        if ($attempt > 1) {
            $this->sleeper->sleepMilliseconds($this->retryPolicy->backoffBeforeAttempt($attempt));
        }

        $startedAt = $this->timer->nanoseconds();

        try {
            $decision = $this->gateway->authorize($request);
        } catch (AcquirerTemporarilyUnavailable $error) {
            $this->recordAttempt($transaction, $attempt, AcquirerOutcome::TEMPORARY_ERROR, $startedAt, $error->errorCode);
            $this->transactions->save($transaction);

            return false;
        } catch (AcquirerIntegrationFailed $error) {
            $this->recordAttempt($transaction, $attempt, AcquirerOutcome::INTEGRATION_ERROR, $startedAt, $error->errorCode);
            $transaction->fail(DeclineReason::ACQUIRER_ERROR, $this->clock->now());
            $this->transactions->save($transaction);

            return true;
        }

        $this->recordAttempt($transaction, $attempt, $decision->outcome, $startedAt, null);
        $decision->applyTo($transaction, $this->clock->now());
        $this->transactions->save($transaction);

        return true;
    }

    private function recordAttempt(
        Transaction $transaction,
        int $attempt,
        AcquirerOutcome $outcome,
        int $startedAt,
        ?string $errorCode,
    ): void {
        $elapsedNanoseconds = $this->timer->nanoseconds() - $startedAt;

        $transaction->recordAcquirerAttempt(
            $attempt,
            $outcome,
            intdiv($elapsedNanoseconds, self::NANOSECONDS_PER_MILLISECOND),
            $errorCode,
            $this->clock->now(),
        );
    }
}
