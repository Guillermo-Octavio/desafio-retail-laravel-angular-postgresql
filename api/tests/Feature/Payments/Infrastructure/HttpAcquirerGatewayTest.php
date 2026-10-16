<?php

declare(strict_types=1);

use App\Payments\Domain\AcquirerOutcome;
use App\Payments\Domain\DeclineReason;
use App\Payments\Domain\Exceptions\AcquirerIntegrationFailed;
use App\Payments\Domain\Exceptions\AcquirerTemporarilyUnavailable;
use App\Payments\Domain\Ports\AcquirerGateway;
use App\Payments\Infrastructure\Acquirer\HttpAcquirerGateway;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Tests\Support\PaymentFixtures;

beforeEach(function () {
    config(['payments.acquirer.base_url' => 'http://acquirer-mock:8000']);
    $this->authorizeUrl = 'http://acquirer-mock:8000/acquirer/authorize';
    $this->gateway = app(AcquirerGateway::class);
});

it('is the adapter bound to the acquirer port', function () {
    expect($this->gateway)->toBeInstanceOf(HttpAcquirerGateway::class);
});

it('sends the authorization request with the correlation id', function () {
    Http::fake([$this->authorizeUrl => Http::response(['status' => 'APPROVED', 'authorization_code' => 'A1B2C3'])]);

    $this->gateway->authorize(PaymentFixtures::acquirerRequest());

    Http::assertSent(fn (Request $request) => $request->url() === $this->authorizeUrl
        && $request->method() === 'POST'
        && $request->header('X-Correlation-Id') === ['corr-123']
        && $request->data() === [
            'transaction_id' => '0199f0c2-6a1e-7c3a-9b2f-5d1e8a4c7b10',
            'merchant_id' => 'merchant-001',
            'amount' => 15000,
            'currency' => 'CLP',
            'card' => ['number' => '4111111111111111', 'expiry_month' => 12, 'expiry_year' => 2030, 'cvv' => '123'],
        ]);
});

it('returns an approval with its authorization code', function () {
    Http::fake([$this->authorizeUrl => Http::response(['status' => 'APPROVED', 'authorization_code' => 'A1B2C3'])]);

    $decision = $this->gateway->authorize(PaymentFixtures::acquirerRequest());

    expect($decision->outcome)->toBe(AcquirerOutcome::APPROVED)
        ->and($decision->authorizationReference)->toBe('A1B2C3');
});

it('returns a decline with its reason', function () {
    Http::fake([$this->authorizeUrl => Http::response(['status' => 'DECLINED', 'reason' => 'INSUFFICIENT_FUNDS'])]);

    $decision = $this->gateway->authorize(PaymentFixtures::acquirerRequest());

    expect($decision->outcome)->toBe(AcquirerOutcome::DECLINED)
        ->and($decision->declineReason)->toBe(DeclineReason::INSUFFICIENT_FUNDS);
});

it('treats server errors, timeouts and connection failures as temporary', function (Closure $response, string $errorCode) {
    Http::fake([$this->authorizeUrl => $response]);

    try {
        $this->gateway->authorize(PaymentFixtures::acquirerRequest());
    } catch (AcquirerTemporarilyUnavailable $error) {
        expect($error->errorCode)->toBe($errorCode);

        return;
    }

    test()->fail('AcquirerTemporarilyUnavailable was not thrown.');
})->with([
    '503' => [fn () => Http::response(['error' => 'TEMPORARY_UNAVAILABLE'], 503), 'HTTP_503'],
    '500' => [fn () => Http::response('', 500), 'HTTP_500'],
    'timeout' => [fn () => throw new ConnectionException('cURL error 28: Operation timed out after 2001 milliseconds'), 'TIMEOUT'],
    'connection refused' => [fn () => throw new ConnectionException('cURL error 7: Failed to connect to acquirer-mock port 8000'), 'CONNECTION_FAILED'],
    'body cut after a 503 status line' => [fn () => throw new RequestException(new Response(new Psr7Response(503, [], 'partial'))), 'HTTP_503'],
]);

it('treats any other unexpected answer as an integration error', function (Closure $response, string $errorCode) {
    Http::fake([$this->authorizeUrl => $response]);

    try {
        $this->gateway->authorize(PaymentFixtures::acquirerRequest());
    } catch (AcquirerIntegrationFailed $error) {
        expect($error->errorCode)->toBe($errorCode);

        return;
    }

    test()->fail('AcquirerIntegrationFailed was not thrown.');
})->with([
    '422' => [fn () => Http::response(['message' => 'invalid'], 422), 'HTTP_422'],
    '404' => [fn () => Http::response('', 404), 'HTTP_404'],
    'redirect, never followed' => [fn () => Http::response('', 302, ['Location' => 'http://elsewhere']), 'HTTP_302'],
    'body is not JSON' => [fn () => Http::response('<html>ok</html>', 200), 'UNEXPECTED_RESPONSE'],
    'unknown status' => [fn () => Http::response(['status' => 'PENDING_REVIEW']), 'UNEXPECTED_RESPONSE'],
    'approval without code' => [fn () => Http::response(['status' => 'APPROVED']), 'UNEXPECTED_RESPONSE'],
    'approval with a code too long' => [fn () => Http::response(['status' => 'APPROVED', 'authorization_code' => str_repeat('A', 65)]), 'UNEXPECTED_RESPONSE'],
    'unknown decline reason' => [fn () => Http::response(['status' => 'DECLINED', 'reason' => 'DO_NOT_HONOR']), 'UNEXPECTED_RESPONSE'],
    'body cut after a 422 status line' => [fn () => throw new RequestException(new Response(new Psr7Response(422, [], 'card 4111111111111111'))), 'HTTP_422'],
    'decline with an internal reason' => [fn () => Http::response(['status' => 'DECLINED', 'reason' => 'AMOUNT_LIMIT_EXCEEDED']), 'UNEXPECTED_RESPONSE'],
]);

it('never puts card data in its errors', function () {
    Http::fake([$this->authorizeUrl => Http::response(['message' => 'invalid card 4111111111111111'], 422)]);

    try {
        $this->gateway->authorize(PaymentFixtures::acquirerRequest());
    } catch (AcquirerIntegrationFailed $error) {
        expect($error->getMessage())->not->toContain('4111111111111111')
            ->and($error->getPrevious()?->getMessage() ?? '')->not->toContain('4111111111111111');

        return;
    }

    test()->fail('AcquirerIntegrationFailed was not thrown.');
});

it('never follows a redirect, so the card data is not sent to another host', function () {
    Http::fake([
        $this->authorizeUrl => Http::response('', 302, ['Location' => 'http://elsewhere/collect']),
        'elsewhere/*' => Http::response(['status' => 'APPROVED', 'authorization_code' => 'A1B2C3']),
    ]);

    expect(fn () => $this->gateway->authorize(PaymentFixtures::acquirerRequest()))->toThrow(AcquirerIntegrationFailed::class);

    Http::assertSentCount(1);
});
