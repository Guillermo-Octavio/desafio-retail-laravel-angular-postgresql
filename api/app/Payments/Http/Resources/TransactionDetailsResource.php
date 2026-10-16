<?php

declare(strict_types=1);

namespace App\Payments\Http\Resources;

use App\Payments\Application\Queries\TransactionDetails;
use App\Payments\Application\Queries\TransactionEventView;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The creation representation plus the history of the transaction (HU-07, HU-09).
 *
 * @property-read TransactionDetails $resource
 */
final class TransactionDetailsResource extends JsonResource
{
    // Same top level as the creation response, which is not wrapped.
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...(new TransactionResource($this->resource->summary))->toArray($request),
            'events' => array_map(fn (TransactionEventView $event) => [
                'type' => $event->type,
                'from_status' => $event->fromStatus,
                'to_status' => $event->toStatus,
                'reason' => $event->reason,
                'metadata' => $event->metadata,
                'correlation_id' => $event->correlationId,
                'occurred_at' => $event->occurredAt->format(DateTimeInterface::RFC3339_EXTENDED),
            ], $this->resource->events),
        ];
    }
}
