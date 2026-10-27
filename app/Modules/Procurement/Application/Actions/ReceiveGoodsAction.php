<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Application\Actions;

use App\Modules\Catalog\Application\Contracts\InventoryGatewayInterface;
use App\Modules\Procurement\Domain\AverageCost;
use App\Modules\Procurement\Domain\Exceptions\ProcurementException;
use App\Modules\Procurement\Domain\LandedCostAllocator;
use App\Modules\Procurement\Domain\PurchaseOrderStatus;
use App\Modules\Procurement\Infrastructure\Models\GoodsReceipt;
use App\Modules\Procurement\Infrastructure\Models\OfferCost;
use App\Modules\Procurement\Infrastructure\Models\PurchaseOrder;
use App\Modules\Procurement\Infrastructure\Models\PurchaseOrderLine;
use App\Modules\Shared\Events\PurchaseReceived;
use Illuminate\Support\Facades\DB;

/**
 * Receives goods against a purchase order, in whole or in part (cahier §6.4):
 * each line is checked (accepted vs refused at inspection), the landed costs
 * are allocated, the weighted-average cost of every accepted offer is updated,
 * and PurchaseReceived tells Inventory to stock the goods and Accounting to
 * book their value — all in one transaction.
 */
final class ReceiveGoodsAction
{
    public function __construct(private readonly InventoryGatewayInterface $inventory) {}

    /**
     * @param  list<array{line_id: int, accepted: int, rejected: int}>  $received
     */
    public function execute(PurchaseOrder $order, array $received, ?string $notes, ?int $receivedBy): GoodsReceipt
    {
        return DB::transaction(function () use ($order, $received, $notes, $receivedBy) {
            // Lock the order so two simultaneous receipts cannot both claim the same outstanding units.
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
            $order->load('lines', 'landedCosts');

            if (! $order->status->acceptsReceipts()) {
                throw ProcurementException::notReceivable($order->number(), $order->status);
            }

            /** @var array<int, PurchaseOrderLine> $linesById */
            $linesById = $order->lines->keyBy('id')->all();
            $touched = [];

            foreach ($received as $row) {
                $line = $linesById[$row['line_id']] ?? throw ProcurementException::lineNotOnOrder($row['line_id']);

                if ($row['accepted'] + $row['rejected'] > $line->outstanding()) {
                    throw ProcurementException::exceedsOutstanding($line->id, $line->outstanding());
                }

                if ($row['accepted'] + $row['rejected'] > 0) {
                    $touched[$line->id] = $row;
                }
            }

            if ($touched === []) {
                throw ProcurementException::nothingReceived();
            }

            // Landed costs spread on cumulative value, so partial receipts add up exactly.
            $goodsTotal = $order->goodsTotal();
            $valueBefore = (int) $order->lines->sum(fn (PurchaseOrderLine $l) => $l->quantity_received * $l->unit_cost_amount);
            $lineValues = [];
            foreach ($touched as $lineId => $row) {
                $lineValues[$lineId] = $row['accepted'] * $linesById[$lineId]->unit_cost_amount;
            }
            $receiptValue = array_sum($lineValues);

            // What the earlier receipts really carried, so a cost added since catches up here.
            $alreadyAllocated = (int) GoodsReceipt::query()->where('purchase_order_id', $order->id)->sum('landed_amount');
            $landedShare = LandedCostAllocator::shareForReceipt(
                $order->landedTotal(), $goodsTotal, $valueBefore + $receiptValue, $alreadyAllocated,
            );
            $landedByLine = array_combine(
                array_keys($lineValues),
                LandedCostAllocator::splitAcrossLines($landedShare, array_values($lineValues)),
            );

            $receipt = GoodsReceipt::query()->create([
                'purchase_order_id' => $order->id,
                'goods_amount' => $receiptValue,
                'landed_amount' => $landedShare,
                'notes' => $notes,
                'received_by' => $receivedBy,
            ]);

            $items = [];

            foreach ($touched as $lineId => $row) {
                $line = $linesById[$lineId];

                $receipt->lines()->create([
                    'purchase_order_line_id' => $lineId,
                    'quantity_accepted' => $row['accepted'],
                    'quantity_rejected' => $row['rejected'],
                    'landed_amount' => $landedByLine[$lineId],
                ]);

                if ($row['accepted'] > 0) {
                    $this->updateAverageCost($line, $row['accepted'], $lineValues[$lineId] + $landedByLine[$lineId]);
                    $items[] = ['offer_id' => $line->offer_id, 'quantity' => $row['accepted']];
                }

                $line->quantity_received += $row['accepted'];
                $line->quantity_rejected += $row['rejected'];
                $line->save();
            }

            $outstanding = (int) $order->lines->sum(fn (PurchaseOrderLine $l) => $l->outstanding());
            $handled = (int) $order->lines->sum(fn (PurchaseOrderLine $l) => $l->quantity_received + $l->quantity_rejected);
            $order->status = PurchaseOrderStatus::afterReceipt($outstanding, $handled);
            $order->save();

            // Only accepted goods enter stock and the books; a refusal-only receipt moves neither.
            if ($items !== []) {
                event(new PurchaseReceived(
                    $receipt->id, $order->number(), $items, $receiptValue, $landedShare, $order->currency,
                ));
            }

            return $receipt->load('lines');
        });
    }

    /**
     * Re-averages the offer's unit cost. The stock on hand is read BEFORE the
     * PurchaseReceived listener adds the new units, which is what the average weights.
     */
    private function updateAverageCost(PurchaseOrderLine $line, int $accepted, int $totalCost): void
    {
        $onHand = $this->inventory->find($line->offer_id)?->stockQuantity ?? 0;
        $current = OfferCost::query()->find($line->offer_id)?->average_cost_amount ?? 0;

        OfferCost::query()->updateOrCreate(
            ['offer_id' => $line->offer_id],
            ['average_cost_amount' => AverageCost::next($onHand, $current, $accepted, $totalCost)],
        );
    }
}
