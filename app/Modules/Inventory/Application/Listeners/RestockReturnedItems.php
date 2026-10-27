<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Listeners;

use App\Modules\Inventory\Application\Actions\RecordStockMovementAction;
use App\Modules\Shared\Events\ReturnReceived;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Puts returned, resellable articles back in stock, each as an audited
 * movement (cahier §6.3 "retour client").
 */
final class RestockReturnedItems
{
    public function __construct(private readonly RecordStockMovementAction $movements) {}

    public function handle(ReturnReceived $event): void
    {
        foreach ($event->items as $item) {
            try {
                $this->movements->execute(
                    $item['offer_id'],
                    $item['quantity'],
                    'Retour RMA-'.sprintf('%06d', $event->returnId).' ('.$event->orderNumber.')',
                    null,
                );
            } catch (ModelNotFoundException) {
                // The offer no longer exists: nothing to restock, and the
                // return itself must not fail because of it.
            }
        }
    }
}
