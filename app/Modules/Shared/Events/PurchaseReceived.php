<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events;

/**
 * Published by Procurement when goods are received. Inventory puts the
 * accepted quantities in stock; Accounting books the stock value (goods +
 * landed costs) against what is owed.
 */
final readonly class PurchaseReceived implements DomainEvent
{
    /**
     * @param  list<array{offer_id: int, quantity: int}>  $items  accepted quantities only
     */
    public function __construct(
        public int $receiptId,
        public string $purchaseNumber,
        public array $items,
        public int $goodsAmount,
        public int $landedAmount,
        public string $currency,
    ) {}
}
