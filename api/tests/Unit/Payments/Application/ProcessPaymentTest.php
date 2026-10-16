<?php

declare(strict_types=1);

use App\Payments\Application\AcquirerAuthorization;
use App\Payments\Application\Exceptions\IdempotencyKeyInProgress;
use App\Payments\Application\Exceptions\IdempotencyKeyReused;
use App\Payments\Application\ProcessPayment;
use App\Payments\Application\RetryPolicy;
use App\Payments\Domain\Acquirer\AcquirerDecision;
use App\Payments\Domain\Currency;
use App\Payments\Domain\DeclineReason;
use App\Payments\Domain\Exceptions\AcquirerIntegrationFailed;
use App\Payments\Domain\Exceptions\AcquirerTemporarilyUnavailable;
use App\Payments\Domain\MerchantId;
use App\Payments\Domain\Rules\AmountLimits;
use App\Payments\Domain\Rules\BusinessRules;
use App\Payments\Domain\TransactionStatus;
use App\Payments\Infrastructure\HmacRequestFingerprinter;
use Tests\Support\Fakes\ArrayResponsePresenter;
use Tests\Support\Fakes\InMemoryIdempotencyStore;
use Tests\Support\Fakes\InMemoryTransactionRepository;
use Tests\Support\Fakes\RecordingSleeper;
use Tests\Support\Fakes\ScriptedAcquirerGateway;
use Tests\Support\Fakes\SequentialTransactionIdGenerator;
use Tests\Support\PaymentFixtures;
use Tests\Support\SteppingClock;

beforeEach(function () {
    $this->transactions = new InMemoryTransactionRepository;
    $this->idempotency = new InMemoryIdempotencyStore;
    $this->sleeper = new RecordingSleeper;
    $this->clock = new SteppingClock('2026-10-15 12:00:00', 5);

    $this->processPayment = function (ScriptedAcquirerGateway $gateway): ProcessPayment {
        $rules = BusinessRules::standard($this->clock, new AmountLimits(['CLP' => 5000000, 'USD' => 500000]));
        $authorization = new AcquirerAuthorization(
            $gateway,
            new RetryPolicy(3, 200, 0, PaymentFixtures::seededRandomizer()),
            $this->sleeper,
            $this->clock,
            $this->transactions,
        );

        return new ProcessPayment(
            $this->idempotency,
            new HmacRequestFingerprinter('test-secret'),
            $this->transactions,
            new SequentialTransactionIdGenerator,
            $this->clock,
            $rules,
            $authorization,
            new ArrayResponsePresenter,
        );
    };
});

it('approves a valid payment authorized by the acquirer', function () {
    $gateway = new ScriptedAcquirerGateway(AcquirerDecision::approved('A1B2C3'));

    $result = ($this->processPayment)($gateway)->execute(PaymentFixtures::command());

    expect($result->replayed)->toBeFalse()
        ->and($result->response->statusCode)->toBe(201)
        ->and($result->response->body['status'])->toBe('APPROVED')
        ->and($gateway->calls())->toBe(1)
        ->and($this->transactions->savedStatuses)->toBe([TransactionStatus::PENDING, TransactionStatus::PROCESSING, TransactionStatus::APPROVED])
        ->and($this->sleeper->sleeps)->toBe([]);
});

it('sends the transaction, the card and the correlation id to the acquirer', function () {
    $gateway = new ScriptedAcquirerGateway(AcquirerDecision::approved('A1B2C3'));

    $result = ($this->processPayment)($gateway)->execute(PaymentFixtures::command(correlationId: 'corr-777'));
    $request = $gateway->requests[0];

    expect($request->transactionId->value)->toBe($result->response->body['transaction_id'])
        ->and($request->merchantId->value)->toBe('merchant-001')
        ->and($request->money->amount)->toBe(15000)
        ->and($request->card->number->reveal())->toBe(PaymentFixtures::VALID_CARD)
        ->and($request->card->cvv->reveal())->toBe('123')
        ->and($request->correlationId->value)->toBe('corr-777');
});

it('commits the PROCESSING state before calling the acquirer', function () {
    $gateway = new ScriptedAcquirerGateway(function () {
        expect($this->transactions->lastSavedStatus())->toBe(TransactionStatus::PROCESSING);

        return AcquirerDecision::approved('A1B2C3');
    });

    ($this->processPayment)($gateway)->execute(PaymentFixtures::command());
});

it('declines a payment that breaks a business rule without calling the acquirer', function (array $change, DeclineReason $reason) {
    $gateway = new ScriptedAcquirerGateway;

    $result = ($this->processPayment)($gateway)->execute(PaymentFixtures::command(...$change));

    expect($result->response->body['status'])->toBe('DECLINED')
        ->and($result->response->body['decline_reason'])->toBe($reason->value)
        ->and($gateway->calls())->toBe(0)
        ->and($this->transactions->savedStatuses)->toBe([TransactionStatus::PENDING, TransactionStatus::DECLINED]);
})->with([
    'amount over the limit' => [['amount' => 5000001], DeclineReason::AMOUNT_LIMIT_EXCEEDED],
    'card number fails Luhn' => [['cardNumber' => PaymentFixtures::LUHN_INVALID_CARD], DeclineReason::INVALID_CARD_NUMBER],
    'expired card' => [['expiryMonth' => 9, 'expiryYear' => 2026], DeclineReason::CARD_EXPIRED],
]);

it('respects a decline from the acquirer and never retries it', function () {
    $gateway = new ScriptedAcquirerGateway(AcquirerDecision::declined(DeclineReason::INSUFFICIENT_FUNDS));

    $result = ($this->processPayment)($gateway)->execute(PaymentFixtures::command(cardNumber: '4000000000000002'));

    expect($result->response->body['status'])->toBe('DECLINED')
        ->and($result->response->body['decline_reason'])->toBe('INSUFFICIENT_FUNDS')
        ->and($gateway->calls())->toBe(1);
});

it('retries a temporary error with backoff and takes the result of the attempt that succeeds', function () {
    $gateway = new ScriptedAcquirerGateway(new AcquirerTemporarilyUnavailable('TIMEOUT'), AcquirerDecision::approved('A1B2C3'));

    $result = ($this->processPayment)($gateway)->execute(PaymentFixtures::command());

    expect($result->response->body['status'])->toBe('APPROVED')
        ->and($gateway->calls())->toBe(2)
        ->and($this->sleeper->sleeps)->toBe([200])
        ->and(PaymentFixtures::acquirerAttempts($this->transactions->events))->toBe([
            ['attempt' => 1, 'outcome' => 'TEMPORARY_ERROR', 'duration_ms' => 5, 'error_code' => 'TIMEOUT'],
            ['attempt' => 2, 'outcome' => 'APPROVED', 'duration_ms' => 5],
        ]);
});

it('fails as ACQUIRER_UNAVAILABLE after three temporary errors', function () {
    $gateway = new ScriptedAcquirerGateway(
        new AcquirerTemporarilyUnavailable('HTTP_503'),
        new AcquirerTemporarilyUnavailable('TIMEOUT'),
        new AcquirerTemporarilyUnavailable('CONNECTION_FAILED'),
    );

    $result = ($this->processPayment)($gateway)->execute(PaymentFixtures::command());

    expect($result->response->statusCode)->toBe(201)
        ->and($result->response->body['status'])->toBe('FAILED')
        ->and($result->response->body['decline_reason'])->toBe('ACQUIRER_UNAVAILABLE')
        ->and($gateway->calls())->toBe(3)
        ->and($this->sleeper->sleeps)->toBe([200, 400])
        ->and(array_column(PaymentFixtures::acquirerAttempts($this->transactions->events), 'error_code'))->toBe(['HTTP_503', 'TIMEOUT', 'CONNECTION_FAILED']);
});

it('saves each failed attempt before retrying', function () {
    $gateway = new ScriptedAcquirerGateway(
        new AcquirerTemporarilyUnavailable('TIMEOUT'),
        function () {
            expect(PaymentFixtures::acquirerAttempts($this->transactions->events))->toHaveCount(1);

            return AcquirerDecision::approved('A1B2C3');
        },
    );

    ($this->processPayment)($gateway)->execute(PaymentFixtures::command());
});

it('fails as ACQUIRER_ERROR without retrying when the acquirer rejects the integration', function () {
    $gateway = new ScriptedAcquirerGateway(new AcquirerIntegrationFailed('HTTP_422'));

    $result = ($this->processPayment)($gateway)->execute(PaymentFixtures::command());

    expect($result->response->body['status'])->toBe('FAILED')
        ->and($result->response->body['decline_reason'])->toBe('ACQUIRER_ERROR')
        ->and($gateway->calls())->toBe(1)
        ->and(PaymentFixtures::acquirerAttempts($this->transactions->events))->toBe([
            ['attempt' => 1, 'outcome' => 'INTEGRATION_ERROR', 'duration_ms' => 5, 'error_code' => 'HTTP_422'],
        ]);
});

it('links the transaction to the idempotency key and stores the final response', function () {
    $gateway = new ScriptedAcquirerGateway(AcquirerDecision::approved('A1B2C3'));

    $result = ($this->processPayment)($gateway)->execute(PaymentFixtures::command());
    $key = $this->idempotency->keys["merchant-001\0key-001"];

    expect($key['transactionId']->value)->toBe($result->response->body['transaction_id'])
        ->and($key['response'])->toBe($result->response);
});

it('replays the original response for the same key and content without charging again', function () {
    $gateway = new ScriptedAcquirerGateway(AcquirerDecision::approved('A1B2C3'));
    $processPayment = ($this->processPayment)($gateway);

    $original = $processPayment->execute(PaymentFixtures::command());
    $retry = $processPayment->execute(PaymentFixtures::command(correlationId: 'corr-retry'));

    expect($retry->replayed)->toBeTrue()
        ->and($retry->response)->toEqual($original->response)
        ->and($gateway->calls())->toBe(1)
        ->and($this->transactions->count())->toBe(1);
});

it('rejects the same key with different content', function () {
    $processPayment = ($this->processPayment)(new ScriptedAcquirerGateway(AcquirerDecision::approved('A1B2C3')));
    $processPayment->execute(PaymentFixtures::command(amount: 15000));

    expect(fn () => $processPayment->execute(PaymentFixtures::command(amount: 20000)))->toThrow(IdempotencyKeyReused::class)
        ->and($this->transactions->count())->toBe(1);
});

it('rejects the same key while the first request is still in progress', function () {
    $this->idempotency->reserve(
        new MerchantId('merchant-001'),
        PaymentFixtures::command()->idempotencyKey,
        (new HmacRequestFingerprinter('test-secret'))->fingerprint(PaymentFixtures::command()),
    );
    $gateway = new ScriptedAcquirerGateway;

    expect(fn () => ($this->processPayment)($gateway)->execute(PaymentFixtures::command()))->toThrow(IdempotencyKeyInProgress::class)
        ->and($gateway->calls())->toBe(0)
        ->and($this->transactions->count())->toBe(0);
});

it('lets two merchants use the same key independently', function () {
    $gateway = new ScriptedAcquirerGateway(AcquirerDecision::approved('A1B2C3'), AcquirerDecision::approved('D4E5F6'));
    $processPayment = ($this->processPayment)($gateway);

    $first = $processPayment->execute(PaymentFixtures::command(merchantId: 'merchant-001'));
    $second = $processPayment->execute(PaymentFixtures::command(merchantId: 'merchant-002'));

    expect($second->replayed)->toBeFalse()
        ->and($second->response->body['transaction_id'])->not->toBe($first->response->body['transaction_id'])
        ->and($this->transactions->count())->toBe(2);
});

it('accepts amounts in USD cents', function () {
    $gateway = new ScriptedAcquirerGateway(AcquirerDecision::approved('A1B2C3'));

    $result = ($this->processPayment)($gateway)->execute(PaymentFixtures::command(amount: 500000, currency: Currency::USD));

    expect($result->response->body['status'])->toBe('APPROVED')
        ->and($gateway->requests[0]->money->currency)->toBe(Currency::USD);
});
