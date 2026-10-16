<?php

declare(strict_types=1);

namespace App\Payments\Http\Requests;

use App\Payments\Application\ProcessPaymentCommand;
use App\Payments\Domain\Card\CardDetails;
use App\Payments\Domain\Card\CardExpiry;
use App\Payments\Domain\Card\CardNumber;
use App\Payments\Domain\Card\Cvv;
use App\Payments\Domain\CorrelationId;
use App\Payments\Domain\Currency;
use App\Payments\Domain\Idempotency\IdempotencyKey;
use App\Payments\Domain\MerchantId;
use App\Payments\Domain\Money;
use App\Payments\Http\Middleware\RequireIdempotencyKey;
use App\Shared\Http\Middleware\AssignCorrelationId;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\Rule;

/**
 * Input format only (HU-02); business rules such as Luhn or the amount limit belong to the domain.
 */
final class CreatePaymentRequest extends FormRequest
{
    private const string IDEMPOTENCY_KEY_FIELD = 'idempotency_key';

    /**
     * The idempotency key comes in a header and is validated with the body.
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return [...$this->all(), self::IDEMPOTENCY_KEY_FIELD => $this->header(RequireIdempotencyKey::HEADER)];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            self::IDEMPOTENCY_KEY_FIELD => ['required', 'string', 'max:255'],
            'merchant_id' => ['required', 'string', 'max:64'],
            // Strict: 10.50 and "1050" are rejected, amounts are integers in minor units (SUP-02).
            'amount' => ['required', 'integer:strict', 'min:1'],
            'currency' => ['required', 'string', Rule::enum(Currency::class)],
            'card' => ['required', 'array'],
            'card.number' => ['required', 'string', 'regex:/^\d{13,19}$/'],
            'card.holder_name' => ['required', 'string', 'max:255'],
            'card.expiry_month' => ['required', 'integer:strict', 'between:1,12'],
            'card.expiry_year' => ['required', 'integer:strict', 'between:1000,9999'],
            'card.cvv' => ['required', 'string', 'regex:/^\d{3,4}$/'],
        ];
    }

    public function toCommand(): ProcessPaymentCommand
    {
        return new ProcessPaymentCommand(
            new MerchantId($this->string('merchant_id')->value()),
            new IdempotencyKey((string) $this->header(RequireIdempotencyKey::HEADER)),
            new CorrelationId((string) Context::get(AssignCorrelationId::CONTEXT_KEY)),
            new Money($this->integer('amount'), Currency::from($this->string('currency')->value())),
            new CardDetails(
                new CardNumber($this->string('card.number')->value()),
                new CardExpiry($this->integer('card.expiry_month'), $this->integer('card.expiry_year')),
                new Cvv($this->string('card.cvv')->value()),
            ),
            $this->string('card.holder_name')->value(),
        );
    }
}
