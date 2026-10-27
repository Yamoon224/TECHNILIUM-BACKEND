<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Application\Contracts;

/**
 * Procurement's public read of the cost of stock (cahier §6.3 CMP), for
 * margin and cost-of-sales figures.
 */
interface OfferCostInterface
{
    /** Weighted-average unit cost of an own-stock offer, 0 when it was never purchased through Procurement. */
    public function averageCost(int $offerId): int;
}
