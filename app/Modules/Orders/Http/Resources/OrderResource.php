<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Resources;

use App\Modules\Orders\Domain\OrderStatusDeriver;
use App\Modules\Orders\Infrastructure\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Order $order */
        $order = $this->resource;

        return [
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'payment_method' => $order->payment_method,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'delivery_address' => $order->delivery_address,
            'delivery_city' => $order->delivery_city,
            'notes' => $order->notes,
            'subtotal' => ['amount' => $order->subtotal_amount, 'currency' => $order->currency],
            // Present once sub-orders are loaded: one entry per seller, each
            // with its own status. `partial` = the shares are at different stages.
            'sub_orders' => $this->whenLoaded('subOrders', fn () => $order->subOrders->map(fn ($subOrder) => [
                'id' => $subOrder->id,
                'vendor_id' => $subOrder->vendor_id,
                'status' => $subOrder->status->value,
                'subtotal' => ['amount' => $subOrder->subtotal_amount, 'currency' => $subOrder->currency],
                'delivered_at' => $subOrder->delivered_at?->toIso8601String(),
            ])->all()),
            'partial' => $this->whenLoaded('subOrders', fn () => OrderStatusDeriver::isPartial(
                $order->subOrders->map(fn ($subOrder) => $subOrder->status)->all(),
            )),
            'items' => $this->whenLoaded('items', fn () => OrderItemResource::collection($order->items)),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}
