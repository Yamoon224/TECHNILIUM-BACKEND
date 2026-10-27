<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events;

/**
 * Published by Procurement when returned, resellable goods re-enter own
 * stock: the cost of sales recognised for them is given back. Accounting
 * reverses it (stock up, cost of sales down).
 */
final readonly class StockReturned implements DomainEvent
{
    public function __construct(
        public int $returnId,
        public int $orderId,
        public string $orderNumber,
        public int $costAmount,
        public string $currency,
    ) {}
}
