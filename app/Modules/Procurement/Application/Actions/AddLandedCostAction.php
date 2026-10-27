<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Application\Actions;

use App\Modules\Procurement\Domain\Exceptions\ProcurementException;
use App\Modules\Procurement\Domain\PurchaseOrderStatus;
use App\Modules\Procurement\Infrastructure\Models\LandedCost;
use App\Modules\Procurement\Infrastructure\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

/**
 * Records a landed cost (transport, customs, handling) on a purchase. It is
 * spread over the goods at each receipt, so it can be added any time until the
 * order is fully received: after that there is nothing left to allocate it to.
 */
final class AddLandedCostAction
{
    public function execute(PurchaseOrder $order, string $label, int $amount): LandedCost
    {
        return DB::transaction(function () use ($order, $label, $amount) {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (in_array($order->status, [PurchaseOrderStatus::Received, PurchaseOrderStatus::Cancelled], true)) {
                throw ProcurementException::notReceivable($order->number(), $order->status);
            }

            return $order->landedCosts()->create(['label' => $label, 'amount' => $amount]);
        });
    }
}
