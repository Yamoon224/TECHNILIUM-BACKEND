<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Domain\OrderStatusDeriver;
use App\Modules\Orders\Infrastructure\Models\Order;

/**
 * Keeps the stored parent status equal to what its sub-orders imply, so
 * every existing filter and reader of orders.status stays valid.
 */
final class OrderStatusSynchronizer
{
    public function sync(Order $order): void
    {
        $subOrders = $order->subOrders()->get();
        $derived = OrderStatusDeriver::derive($subOrders->map(fn ($s) => $s->status)->all());

        if ($derived === null) {
            return;
        }

        $order->status = $derived;

        if ($derived->value === 'delivered') {
            $order->delivered_at = $subOrders->max('delivered_at');
        }

        $order->save();
    }
}
