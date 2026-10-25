<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Resources;

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
            'items' => $this->whenLoaded('items', fn () => OrderItemResource::collection($order->items)),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}
