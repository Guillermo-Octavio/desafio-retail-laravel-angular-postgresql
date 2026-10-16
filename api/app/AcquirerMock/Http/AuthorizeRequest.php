<?php

declare(strict_types=1);

namespace App\AcquirerMock\Http;

use Illuminate\Foundation\Http\FormRequest;

final class AuthorizeRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'transaction_id' => ['required', 'uuid'],
            'merchant_id' => ['required', 'string', 'max:64'],
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'card' => ['required', 'array'],
            'card.number' => ['required', 'string', 'regex:/^\d{13,19}\z/'],
            'card.expiry_month' => ['required', 'integer', 'between:1,12'],
            'card.expiry_year' => ['required', 'integer', 'digits:4'],
            'card.cvv' => ['required', 'string', 'regex:/^\d{3,4}\z/'],
        ];
    }
}
