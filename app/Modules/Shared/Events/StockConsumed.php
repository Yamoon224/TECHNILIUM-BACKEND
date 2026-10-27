<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events;

/**
 * Published by Procurement when delivered goods from Technilium's own stock
 * leave it at their average cost; Accounting books the cost of goods sold.
 */
final readonly class StockConsumed implements DomainEvent
{
    public function __construct(
        public int $orderId,
        public string $orderNumber,
        public int $costAmount,
        public string $currency,
    ) {}
}
