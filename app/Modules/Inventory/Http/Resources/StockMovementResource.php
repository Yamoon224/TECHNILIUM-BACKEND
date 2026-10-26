<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Resources;

use App\Modules\Inventory\Infrastructure\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var StockMovement $movement */
        $movement = $this->resource;

        return [
            'id' => $movement->id,
            'offer_id' => $movement->offer_id,
            'created_by' => $movement->created_by,
            'quantity_delta' => $movement->quantity_delta,
            'quantity_after' => $movement->quantity_after,
            'reason' => $movement->reason,
            'created_at' => $movement->created_at?->toIso8601String(),
        ];
    }
}
