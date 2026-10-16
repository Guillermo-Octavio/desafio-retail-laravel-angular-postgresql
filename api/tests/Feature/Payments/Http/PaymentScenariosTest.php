<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\FakeAcquirer;
use Tests\Support\PaymentRows;

beforeEach(function () {
    Carbon::setTestNow('2026-10-15 12:00:00');
    $this->acquirer = FakeAcquirer::install();
    $this->pay = fn (array $overrides = [], array $headers = []) => $this->postJson(
        '/api/payments',
        PaymentRows::paymentBody($overrides),
        ['Idempotency-Key' => 'key-001', ...$headers],
    );
});

describe('HU-01 Enviar un pago', function () {
    it('walks the transaction through PENDING, PROCESSING and APPROVED', function () {
        $transactionId = ($this->pay)()->assertCreated()->json('transaction_id');

        expect(DB::table('transaction_events')->where('transaction_id', $transactionId)->where('type', 'STATUS_CHANGED')->orderBy('id')->pluck('to_status')->all())
            ->toBe(['PENDING', 'PROCESSING', 'APPROVED']);
    });
});

describe('HU-03 Aplicar reglas de negocio', function () {
    it('applies the amount limit of each currency, inclusive', function (string $currency, int $amount, string $status) {
        ($this->pay)(['amount' => $amount, 'currency' => $currency])->assertCreated()->assertJsonPath('status', $status);
    })->with([
        ['CLP', 5000000, 'APPROVED'],
        ['CLP', 5000001, 'DECLINED'],
        ['USD', 1050, 'APPROVED'],
        ['USD', 500000, 'APPROVED'],
        ['USD', 500001, 'DECLINED'],
    ]);

    it('declines a rule breach with its reason and without calling the acquirer', function (array $overrides, string $reason) {
        ($this->pay)($overrides)
            ->assertCreated()
            ->assertJsonPath('status', 'DECLINED')
            ->assertJsonPath('decline_reason', $reason);

        expect($this->acquirer->calls())->toBe(0);
    })->with([
        'amount over the limit' => [['amount' => 5000001], 'AMOUNT_LIMIT_EXCEEDED'],
        'card fails Luhn' => [['card.number' => '4111111111111112'], 'INVALID_CARD_NUMBER'],
        'card expired last month' => [['card.expiry_month' => 9, 'card.expiry_year' => 2026], 'CARD_EXPIRED'],
    ]);

    it('accepts a card that expires this month', function () {
        ($this->pay)(['card.expiry_month' => 10, 'card.expiry_year' => 2026])->assertJsonPath('status', 'APPROVED');
    });

    it('returns amounts in USD cents unchanged', function () {
        ($this->pay)(['amount' => 1050, 'currency' => 'USD'])
            ->assertCreated()
            ->assertJsonPath('amount', 1050)
            ->assertJsonPath('currency', 'USD');
    });
});

describe('HU-04 Respetar la decisión del adquirente', function () {
    it('declines with the acquirer reason after exactly one call', function () {
        ($this->pay)(['card.number' => '4000000000000002'])
            ->assertCreated()
            ->assertJsonPath('status', 'DECLINED')
            ->assertJsonPath('decline_reason', 'INSUFFICIENT_FUNDS');

        expect($this->acquirer->calls())->toBe(1);
    });
});

describe('HU-05 Tolerar errores temporales del adquirente', function () {
    it('recovers after one temporary error and records the failed attempt', function () {
        $transactionId = ($this->pay)(['card.number' => '4000000000000259'])->assertJsonPath('status', 'APPROVED')->json('transaction_id');

        $attempts = DB::table('transaction_events')->where('transaction_id', $transactionId)->where('type', 'ACQUIRER_ATTEMPT')->orderBy('id')->pluck('metadata')
            ->map(fn (string $metadata) => json_decode($metadata, true)['outcome'])->all();

        expect($this->acquirer->calls())->toBe(2)
            ->and($attempts)->toBe(['TEMPORARY_ERROR', 'APPROVED']);
    });

    it('fails as ACQUIRER_UNAVAILABLE after three temporary errors and still answers 201', function () {
        ($this->pay)(['card.number' => '4000000000000119'])
            ->assertCreated()
            ->assertJsonPath('status', 'FAILED')
            ->assertJsonPath('decline_reason', 'ACQUIRER_UNAVAILABLE');

        expect($this->acquirer->calls())->toBe(3);
    });
});

describe('HU-06 Evitar pagos duplicados', function () {
    it('replays the original response without a second transaction or acquirer call', function () {
        $original = ($this->pay)();
        $replay = ($this->pay)();

        $replay->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
        expect($replay->json('transaction_id'))->toBe($original->json('transaction_id'))
            ->and(DB::table('transactions')->where('merchant_id', 'merchant-001')->count())->toBe(1)
            ->and($this->acquirer->calls())->toBe(1);
    });

    it('rejects the same key with different content', function () {
        ($this->pay)(['amount' => 15000]);

        ($this->pay)(['amount' => 20000])->assertStatus(409)->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REUSED');
        expect(DB::table('transactions')->count())->toBe(1);
    });

    it('lets two merchants use the same key', function () {
        ($this->pay)(['merchant_id' => 'merchant-001']);

        ($this->pay)(['merchant_id' => 'merchant-002'])->assertCreated()->assertHeaderMissing('Idempotent-Replayed');
        expect(DB::table('transactions')->where('merchant_id', 'merchant-002')->count())->toBe(1);
    });

    it('answers IN_PROGRESS while the first request with the key has not finished', function () {
        DB::table('idempotency_keys')->insert(PaymentRows::idempotencyKey([
            'idempotency_key' => 'key-001',
            'request_fingerprint' => str_repeat('0', 64),
        ]));

        ($this->pay)()->assertStatus(409);
        expect(DB::table('transactions')->count())->toBe(0);
    });
});

describe('HU-10 Correlacionar logs y eventos', function () {
    it('propagates the client correlation id to the response, the transaction, its events and the acquirer', function () {
        $transactionId = ($this->pay)([], ['X-Correlation-Id' => 'corr-123'])
            ->assertHeader('X-Correlation-Id', 'corr-123')
            ->json('transaction_id');

        expect(DB::table('transactions')->where('id', $transactionId)->value('correlation_id'))->toBe('corr-123')
            ->and(DB::table('transaction_events')->where('transaction_id', $transactionId)->distinct()->pluck('correlation_id')->all())->toBe(['corr-123']);
        Http::assertSent(fn (Request $request) => $request->header('X-Correlation-Id') === ['corr-123']);
    });

    it('generates and stores a UUID when the client sends none', function () {
        $response = ($this->pay)();

        $correlationId = $response->headers->get('X-Correlation-Id');
        expect(Str::isUuid($correlationId))->toBeTrue()
            ->and(DB::table('transactions')->value('correlation_id'))->toBe($correlationId);
    });

    it('returns the correlation id on validation errors', function () {
        ($this->pay)(['amount' => 0], ['X-Correlation-Id' => 'corr-456'])
            ->assertStatus(422)
            ->assertHeader('X-Correlation-Id', 'corr-456');
    });
});

describe('HU-11 Proteger los datos de tarjeta', function () {
    it('never stores or returns the full card number or the CVV', function () {
        $response = ($this->pay)(['card.cvv' => '987'], ['Idempotency-Key' => 'key-cvv']);

        $stored = json_encode([
            DB::table('transactions')->get(),
            DB::table('transaction_events')->get(),
            DB::table('idempotency_keys')->get(),
        ]);

        // The CVV is searched as a JSON string value, so digits inside UUIDs or dates cannot match.
        expect($stored)->not->toContain('4111111111111111')->not->toContain('"987"')->not->toContain('"cvv"')
            ->and($response->getContent())->not->toContain('4111111111111111')->not->toContain('"987"')->not->toContain('"cvv"');
        Http::assertSent(fn (Request $request) => $request['card']['number'] === '4111111111111111' && $request['card']['cvv'] === '987');
    });
});
