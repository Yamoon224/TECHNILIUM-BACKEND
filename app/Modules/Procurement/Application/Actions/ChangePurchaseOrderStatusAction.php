<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Application\Actions;

use App\Modules\Procurement\Domain\Exceptions\ProcurementException;
use App\Modules\Procurement\Domain\PurchaseOrderStatus;
use App\Modules\Procurement\Infrastructure\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

/**
 * Sends a draft to the supplier, or cancels an order. Receiving moves the
 * status by itself (ReceiveGoodsAction), so it is not offered here.
 */
final class ChangePurchaseOrderStatusAction
{
    public function place(PurchaseOrder $order): PurchaseOrder
    {
        return $this->move($order, PurchaseOrderStatus::Ordered);
    }

    public function cancel(PurchaseOrder $order): PurchaseOrder
    {
        return $this->move($order, PurchaseOrderStatus::Cancelled);
    }

    private function move(PurchaseOrder $order, PurchaseOrderStatus $target): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $target) {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (! $order->status->canTransitionTo($target)) {
                throw ProcurementException::invalidTransition($order->status, $target);
            }

            // A cancelled order that already received goods would leave stock with no purchase.
            if ($target === PurchaseOrderStatus::Cancelled && $order->lines()->where(
                fn ($q) => $q->where('quantity_received', '>', 0)->orWhere('quantity_rejected', '>', 0),
            )->exists()) {
                throw ProcurementException::alreadyReceived($order->number());
            }

            $order->status = $target;

            if ($target === PurchaseOrderStatus::Ordered) {
                $order->ordered_at = now();
            }

            $order->save();

            return $order->load('supplier', 'lines', 'landedCosts');
        });
    }
}
