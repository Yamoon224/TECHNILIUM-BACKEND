<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Actions;

use App\Modules\Orders\Domain\Exceptions\InvalidOrderTransitionException;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;

final class TransitionOrderStatusAction
{
    public function execute(Order $order, OrderStatus $target): Order
    {
        if (! $order->status->canTransitionTo($target)) {
            throw InvalidOrderTransitionException::from($order->status, $target);
        }

        $order->status = $target;

        if ($target === OrderStatus::Delivered) {
            // Starts the return window that makes a vendor's share payable (cahier §3.1).
            $order->delivered_at = now();
        }

        $order->save();

        return $order->refresh();
    }
}
