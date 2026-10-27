<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Http\Resources;

use App\Modules\Procurement\Infrastructure\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PurchaseOrder $order */
        $order = $this->resource;
        $loaded = $order->relationLoaded('lines') && $order->relationLoaded('landedCosts');

        return [
            'id' => $order->id,
            'number' => $order->number(),
            'supplier_id' => $order->supplier_id,
            'supplier_name' => $order->relationLoaded('supplier') ? $order->supplier->name : null,
            'status' => $order->status->value,
            'currency' => $order->currency,
            'expected_at' => $order->expected_at?->toDateString(),
            'ordered_at' => $order->ordered_at?->toIso8601String(),
            'notes' => $order->notes,
            'created_at' => $order->created_at?->toIso8601String(),
            'goods_total' => $loaded ? $order->goodsTotal() : null,
            'landed_total' => $loaded ? $order->landedTotal() : null,
            'lines' => $this->whenLoaded('lines', fn () => $order->lines->map(fn ($line) => [
                'id' => $line->id,
                'offer_id' => $line->offer_id,
                'description' => $line->description,
                'quantity_ordered' => $line->quantity_ordered,
                'quantity_received' => $line->quantity_received,
                'quantity_rejected' => $line->quantity_rejected,
                'outstanding' => $line->outstanding(),
                'unit_cost_amount' => $line->unit_cost_amount,
            ])->all()),
            'landed_costs' => $this->whenLoaded('landedCosts', fn () => $order->landedCosts->map(fn ($cost) => [
                'id' => $cost->id,
                'label' => $cost->label,
                'amount' => $cost->amount,
            ])->all()),
            'receipts' => $this->whenLoaded('receipts', fn () => $order->receipts->map(fn ($receipt) => [
                'id' => $receipt->id,
                'goods_amount' => $receipt->goods_amount,
                'landed_amount' => $receipt->landed_amount,
                'notes' => $receipt->notes,
                'created_at' => $receipt->created_at?->toIso8601String(),
                'lines' => $receipt->lines->map(fn ($line) => [
                    'purchase_order_line_id' => $line->purchase_order_line_id,
                    'quantity_accepted' => $line->quantity_accepted,
                    'quantity_rejected' => $line->quantity_rejected,
                    'landed_amount' => $line->landed_amount,
                ])->all(),
            ])->all()),
        ];
    }
}
