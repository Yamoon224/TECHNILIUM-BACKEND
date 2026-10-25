<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Resources;

use App\Modules\Orders\Infrastructure\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OrderItem $item */
        $item = $this->resource;

        return [
            'offer_id' => $item->offer_id,
            'product_name' => $item->product_name,
            'brand' => $item->brand,
            'quantity' => $item->quantity,
            'unit_price' => ['amount' => $item->unit_price_amount, 'currency' => $item->currency],
            'line_total' => ['amount' => $item->line_total_amount, 'currency' => $item->currency],
        ];
    }
}
