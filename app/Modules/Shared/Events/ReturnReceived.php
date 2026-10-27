<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events;

/**
 * Published by Returns when the returned parcel was received, inspected and
 * judged resellable. Inventory puts the articles back in stock.
 */
final readonly class ReturnReceived implements DomainEvent
{
    /**
     * @param  list<array{offer_id: int, quantity: int}>  $items
     */
    public function __construct(
        public int $returnId,
        public string $orderNumber,
        public array $items,
    ) {}
}
