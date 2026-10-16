<?php

declare(strict_types=1);

namespace App\Payments\Http\Resources;

use App\Payments\Application\Queries\TransactionPage;
use App\Payments\Application\Queries\TransactionSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read TransactionPage $resource
 */
final class TransactionPageResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function toArray(Request $request): array
    {
        $page = $this->resource;

        return [
            'data' => array_map(fn (TransactionSummary $item) => (new TransactionResource($item))->toArray($request), $page->items),
            'meta' => [
                'current_page' => $page->page,
                'per_page' => $page->perPage,
                'total' => $page->total,
                'last_page' => $page->lastPage(),
            ],
        ];
    }
}
