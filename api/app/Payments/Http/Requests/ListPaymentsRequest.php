<?php

declare(strict_types=1);

namespace App\Payments\Http\Requests;

use App\Payments\Application\Queries\TransactionFilter;
use App\Payments\Domain\TransactionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListPaymentsRequest extends FormRequest
{
    private const int FIRST_PAGE = 1;

    // Keeps the page offset (page × page size) far from the integer limit; no real listing
    // reaches a million pages.
    private const int MAX_PAGE = 1_000_000;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'merchant_id' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'string', Rule::enum(TransactionStatus::class)],
            'page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PAGE],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function filter(): TransactionFilter
    {
        return new TransactionFilter(
            $this->filled('merchant_id') ? $this->string('merchant_id')->value() : null,
            $this->filled('status') ? TransactionStatus::from($this->string('status')->value()) : null,
        );
    }

    public function page(): int
    {
        return $this->integer('page', self::FIRST_PAGE);
    }

    public function perPage(): ?int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : null;
    }
}
