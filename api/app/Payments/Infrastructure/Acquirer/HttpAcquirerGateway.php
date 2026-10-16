<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Acquirer;

use App\Payments\Domain\Acquirer\AcquirerDecision;
use App\Payments\Domain\Acquirer\AcquirerRequest;
use App\Payments\Domain\DeclineReason;
use App\Payments\Domain\Exceptions\AcquirerIntegrationFailed;
use App\Payments\Domain\Exceptions\AcquirerTemporarilyUnavailable;
use App\Payments\Domain\Ports\AcquirerGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

/**
 * Honors the full port contract: it only ever throws AcquirerTemporarilyUnavailable or
 * AcquirerIntegrationFailed, so a payment never gets stuck in PROCESSING (architecture 5.1).
 */
final readonly class HttpAcquirerGateway implements AcquirerGateway
{
    private const string AUTHORIZE_PATH = '/acquirer/authorize';

    private const string CURL_TIMEOUT_ERROR = 'cURL error 28';

    private const string UNEXPECTED_RESPONSE = 'UNEXPECTED_RESPONSE';

    private const string TIMEOUT = 'TIMEOUT';

    private const string CONNECTION_FAILED = 'CONNECTION_FAILED';

    private const string APPROVED = 'APPROVED';

    private const string DECLINED = 'DECLINED';

    private const int MAX_AUTHORIZATION_CODE_LENGTH = 64;

    /** Reasons the acquirer can decline with; the rest are decided by our own rules. */
    private const array ACQUIRER_DECLINE_REASONS = [DeclineReason::INSUFFICIENT_FUNDS];

    public function __construct(
        private HttpFactory $http,
        private string $baseUrl,
        private int $connectTimeoutSeconds,
        private int $timeoutSeconds,
    ) {}

    public function authorize(AcquirerRequest $request): AcquirerDecision
    {
        $response = $this->send($request);

        if (! $response->successful()) {
            throw $this->failedStatus($response);
        }

        return $this->decision($response) ?? throw new AcquirerIntegrationFailed(self::UNEXPECTED_RESPONSE);
    }

    private function send(AcquirerRequest $request): Response
    {
        try {
            return $this->http
                ->baseUrl($this->baseUrl)
                ->connectTimeout($this->connectTimeoutSeconds)
                ->timeout($this->timeoutSeconds)
                // A redirect would resend the card data to another host, so it is never followed.
                ->withoutRedirecting()
                ->acceptJson()
                ->withHeaders(['X-Correlation-Id' => $request->correlationId->value])
                ->post(self::AUTHORIZE_PATH, $this->payload($request));
        } catch (ConnectionException $error) {
            $isTimeout = str_contains($error->getMessage(), self::CURL_TIMEOUT_ERROR);

            throw new AcquirerTemporarilyUnavailable($isTimeout ? self::TIMEOUT : self::CONNECTION_FAILED, $error);
        } catch (RequestException $error) {
            // The status line arrived but the body transfer failed. The original exception is
            // dropped on purpose: its message quotes the response body, which may echo card data.
            throw $this->failedStatus($error->response);
        }
    }

    private function failedStatus(Response $response): AcquirerTemporarilyUnavailable|AcquirerIntegrationFailed
    {
        $errorCode = "HTTP_{$response->status()}";

        return $response->serverError()
            ? new AcquirerTemporarilyUnavailable($errorCode)
            : new AcquirerIntegrationFailed($errorCode);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(AcquirerRequest $request): array
    {
        return [
            'transaction_id' => $request->transactionId->value,
            'merchant_id' => $request->merchantId->value,
            'amount' => $request->money->amount,
            'currency' => $request->money->currency->value,
            'card' => [
                'number' => $request->card->number->reveal(),
                'expiry_month' => $request->card->expiry->month,
                'expiry_year' => $request->card->expiry->year,
                'cvv' => $request->card->cvv->reveal(),
            ],
        ];
    }

    private function decision(Response $response): ?AcquirerDecision
    {
        return match ($response->json('status')) {
            self::APPROVED => $this->approval($response->json('authorization_code')),
            self::DECLINED => $this->decline($response->json('reason')),
            default => null,
        };
    }

    private function approval(mixed $authorizationCode): ?AcquirerDecision
    {
        $isValidCode = is_string($authorizationCode)
            && $authorizationCode !== ''
            && strlen($authorizationCode) <= self::MAX_AUTHORIZATION_CODE_LENGTH;

        return $isValidCode ? AcquirerDecision::approved($authorizationCode) : null;
    }

    private function decline(mixed $reason): ?AcquirerDecision
    {
        $declineReason = is_string($reason) ? DeclineReason::tryFrom($reason) : null;

        return in_array($declineReason, self::ACQUIRER_DECLINE_REASONS, true)
            ? AcquirerDecision::declined($declineReason)
            : null;
    }
}
