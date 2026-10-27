<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Actions;

use App\Modules\Orders\Application\OrderStatusSynchronizer;
use App\Modules\Orders\Domain\Exceptions\InvalidOrderTransitionException;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Whole-order transition (staff, or Shipping syncing a shipment). Validated
 * against the parent status, then applied to every sub-order that can legally
 * follow; the parent status is re-derived from them. An order without
 * sub-orders (placed before they existed) is moved directly.
 */
final class TransitionOrderStatusAction
{
    public function __construct(private readonly OrderStatusSynchronizer $synchronizer) {}

    public function execute(Order $order, OrderStatus $target): Order
    {
        if (! $order->status->canTransitionTo($target)) {
            throw InvalidOrderTransitionException::from($order->status, $target);
        }

        DB::transaction(function () use ($order, $target) {
            $subOrders = $order->subOrders()->get();

            if ($subOrders->isEmpty()) {
                $order->status = $target;

                if ($target === OrderStatus::Delivered) {
                    // Starts the return window that makes a vendor's share payable (cahier §3.1).
                    $order->delivered_at = now();
                }

                $order->save();

                return;
            }

            foreach ($subOrders as $subOrder) {
                if ($subOrder->status->canTransitionTo($target)) {
                    $subOrder->status = $target;
                    $subOrder->delivered_at = $target === OrderStatus::Delivered ? now() : $subOrder->delivered_at;
                    $subOrder->save();
                }
            }

            $this->synchronizer->sync($order);
        });

        return $order->refresh()->load('items', 'subOrders');
    }
}
