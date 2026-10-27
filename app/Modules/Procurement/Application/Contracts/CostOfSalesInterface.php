<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Application\Contracts;

use DateTimeInterface;

/**
 * Procurement's public read of the cost of sales, for margin reporting
 * (cahier §6.14 "rapports de marges"). Only own-stock sales have a cost here.
 */
interface CostOfSalesInterface
{
    /**
     * Cost recognised at delivery, per order then per offer. An order with no
     * recorded cost (own stock never purchased through Procurement) is absent.
     *
     * @param  list<int>  $orderIds
     * @return array<int, array<int, int>>  order id => (offer id => cost)
     */
    public function costsByOrderAndOffer(array $orderIds): array;

    /** Cost of sales given back by customer returns restocked in [from, to]. */
    public function reversedBetween(DateTimeInterface $from, DateTimeInterface $to): int;
}
