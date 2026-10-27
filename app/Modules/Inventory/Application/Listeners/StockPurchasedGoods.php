<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Listeners;

use App\Modules\Inventory\Application\Actions\RecordStockMovementAction;
use App\Modules\Shared\Events\PurchaseReceived;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Puts received (accepted) goods into stock, each as an audited movement
 * (cahier §6.3 "entrée (réception)").
 */
final class StockPurchasedGoods
{
    public function __construct(private readonly RecordStockMovementAction $movements) {}

    public function handle(PurchaseReceived $event): void
    {
        foreach ($event->items as $item) {
            try {
                $this->movements->execute($item['offer_id'], $item['quantity'], "Réception {$event->purchaseNumber}", null);
            } catch (ModelNotFoundException) {
                // The offer vanished since the order was drafted: nothing to stock.
            }
        }
    }
}
