<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Application\Actions;

use App\Modules\Catalog\Application\Contracts\InventoryGatewayInterface;
use App\Modules\Procurement\Domain\Exceptions\ProcurementException;
use App\Modules\Procurement\Domain\PurchaseOrderStatus;
use App\Modules\Procurement\Infrastructure\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

/**
 * Drafts a purchase order. Only Technilium's own stock can be bought: an
 * offer sold by a third-party vendor is that vendor's stock, not ours to buy
 * (cahier §3 - 1P vs 3P).
 */
final class CreatePurchaseOrderAction
{
    public function __construct(private readonly InventoryGatewayInterface $inventory) {}

    /**
     * @param  list<array{offer_id: int, quantity: int, unit_cost_amount: int}>  $lines
     */
    public function execute(int $supplierId, array $lines, ?string $expectedAt, ?string $notes, ?int $createdBy): PurchaseOrder
    {
        $described = [];
        $seen = [];

        foreach ($lines as $line) {
            $offerId = $line['offer_id'];

            if (isset($seen[$offerId])) {
                throw ProcurementException::duplicateOffer($offerId);
            }
            $seen[$offerId] = true;

            $offer = $this->inventory->find($offerId);

            if ($offer === null || $offer->vendorId !== null) {
                throw ProcurementException::offerNotPurchasable($offerId);
            }

            $described[$offerId] = trim($offer->brand.' '.$offer->productName).' ('.$offer->sku.')';
        }

        return DB::transaction(function () use ($supplierId, $lines, $expectedAt, $notes, $createdBy, $described) {
            $order = PurchaseOrder::query()->create([
                'supplier_id' => $supplierId,
                'status' => PurchaseOrderStatus::Draft,
                'currency' => 'XOF',
                'expected_at' => $expectedAt,
                'notes' => $notes,
                'created_by' => $createdBy,
            ]);

            foreach ($lines as $line) {
                $order->lines()->create([
                    'offer_id' => $line['offer_id'],
                    'description' => $described[$line['offer_id']],
                    'quantity_ordered' => $line['quantity'],
                    'unit_cost_amount' => $line['unit_cost_amount'],
                ]);
            }

            return $order->load('supplier', 'lines', 'landedCosts');
        });
    }
}
