<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Application\Gateways;

use App\Modules\Procurement\Application\Contracts\CostOfSalesInterface;
use App\Modules\Procurement\Infrastructure\Models\CostReversal;
use App\Modules\Procurement\Infrastructure\Models\OrderCostLine;
use DateTimeInterface;

final class EloquentCostOfSales implements CostOfSalesInterface
{
    public function costsByOrderAndOffer(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        $costs = [];

        foreach (OrderCostLine::query()->whereIn('order_id', $orderIds)->get() as $line) {
            $costs[$line->order_id][$line->offer_id] = $line->cost_amount;
        }

        return $costs;
    }

    public function reversedBetween(DateTimeInterface $from, DateTimeInterface $to): int
    {
        return (int) CostReversal::query()->whereBetween('created_at', [$from, $to])->sum('amount');
    }
}
