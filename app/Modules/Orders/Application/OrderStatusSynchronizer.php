<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Domain\OrderStatusDeriver;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Shared\Events\OrderStatusChanged;

/**
 * Keeps the stored parent status equal to what its sub-orders imply, so
 * every existing filter and reader of orders.status stays valid.
 */
final class OrderStatusSynchronizer
{
    public function __construct(private readonly OrderStatusLog $statusLog) {}

    public function sync(Order $order): void
    {
        $subOrders = $order->subOrders()->get();
        $derived = OrderStatusDeriver::derive($subOrders->map(fn ($s) => $s->status)->all());

        if ($derived === null) {
            return;
        }

        $changed = $order->status !== $derived;
        $order->status = $derived;

        if ($derived->value === 'delivered') {
            $order->delivered_at = $subOrders->max('delivered_at');
        }

        $order->save();

        // Only a real change is announced; a sub-order moving without
        // changing the parent (e.g. one of two parcels shipped) is silent.
        if ($changed) {
            $this->statusLog->record($order, $derived->value);

            event(new OrderStatusChanged($order->order_number, $order->customer_name, $order->customer_phone, $derived->value, $order->id, $order->customer_email, $order->customer_locale ?? 'fr', $order->channel ?? 'online'));
        }
    }
}
