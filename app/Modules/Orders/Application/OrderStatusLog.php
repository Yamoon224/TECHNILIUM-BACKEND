<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderStatusEvent;
use App\Modules\Orders\Infrastructure\Models\SubOrder;

/** Appends to status histories: the order's own, and each parcel's (the customer's tracking timeline). */
final class OrderStatusLog
{
    public function record(Order $order, string $status): void
    {
        OrderStatusEvent::query()->create([
            'order_id' => $order->id,
            'status' => $status,
            'occurred_at' => now(),
        ]);
    }

    public function recordParcel(SubOrder $subOrder, string $status): void
    {
        OrderStatusEvent::query()->create([
            'order_id' => $subOrder->order_id,
            'sub_order_id' => $subOrder->id,
            'status' => $status,
            'occurred_at' => now(),
        ]);
    }
}
