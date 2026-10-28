<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Actions;

use App\Modules\Orders\Application\OrderStatusSynchronizer;
use App\Modules\Orders\Application\OrderStatusLog;
use App\Modules\Orders\Domain\Exceptions\InvalidOrderTransitionException;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\SubOrder;
use Illuminate\Support\Facades\DB;

/**
 * Moves one seller's share forward on its own (cahier §3.2): a vendor ships
 * its goods without waiting for the others. The parent status follows.
 */
final class TransitionSubOrderAction
{
    public function __construct(
        private readonly OrderStatusSynchronizer $synchronizer,
        private readonly OrderStatusLog $statusLog,
    ) {}

    public function execute(SubOrder $subOrder, OrderStatus $target): SubOrder
    {
        return DB::transaction(function () use ($subOrder, $target) {
            // Lock so two concurrent moves cannot both pass the check below.
            $subOrder = SubOrder::query()->lockForUpdate()->findOrFail($subOrder->id);

            if (! $subOrder->status->canTransitionTo($target)) {
                throw InvalidOrderTransitionException::from($subOrder->status, $target);
            }

            $subOrder->status = $target;

            if ($target === OrderStatus::Delivered) {
                $subOrder->delivered_at = now();
            }

            $subOrder->save();

            $this->statusLog->recordParcel($subOrder, $target->value);

            /** @var Order $order */
            $order = Order::query()->findOrFail($subOrder->order_id);
            $this->synchronizer->sync($order);

            return $subOrder;
        });
    }
}
