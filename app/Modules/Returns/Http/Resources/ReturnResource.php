<?php

declare(strict_types=1);

namespace App\Modules\Returns\Http\Resources;

use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ReturnRequest $return */
        $return = $this->resource;

        return [
            'id' => $return->id,
            'number' => $return->number(),
            'order_number' => $return->order_number,
            'status' => $return->status->value,
            'reason' => $return->reason,
            'decision_note' => $return->decision_note,
            'currency' => $return->currency,
            'requested_amount' => $return->requested_amount,
            'refunded_amount' => $return->refunded_amount,
            'items' => $return->items ?? [],
            'restocked' => $return->restocked,
            'refunded_at' => $return->refunded_at?->toIso8601String(),
            'created_at' => $return->created_at?->toIso8601String(),
        ];
    }
}
