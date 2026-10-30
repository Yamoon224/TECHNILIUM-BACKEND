<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Actions;

use App\Modules\Catalog\Application\Contracts\InventoryGatewayInterface;
use App\Modules\Inventory\Infrastructure\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * Single entry point for every manual stock change - applies the delta on
 * Catalog's offer (via its public contract, never its Eloquent models -
 * cahier §9.1) and records the audit trail row atomically.
 */
final class RecordStockMovementAction
{
    public function __construct(
        private readonly InventoryGatewayInterface $inventory,
    ) {}

    public function execute(int $offerId, int $delta, ?string $reason, ?int $createdBy): StockMovement
    {
        return DB::transaction(function () use ($offerId, $delta, $reason, $createdBy): StockMovement {
            $newQuantity = $this->inventory->adjustStock($offerId, $delta);

            return StockMovement::query()->create([
                'offer_id' => $offerId,
                'created_by' => $createdBy,
                'quantity_delta' => $delta,
                'quantity_after' => $newQuantity,
                'reason' => $reason,
            ]);
        });
    }
}
