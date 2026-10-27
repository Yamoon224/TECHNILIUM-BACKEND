<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Application\Gateways;

use App\Modules\Procurement\Application\Contracts\OfferCostInterface;
use App\Modules\Procurement\Infrastructure\Models\OfferCost;

final class EloquentOfferCost implements OfferCostInterface
{
    public function averageCost(int $offerId): int
    {
        return (int) (OfferCost::query()->find($offerId)?->average_cost_amount ?? 0);
    }
}
