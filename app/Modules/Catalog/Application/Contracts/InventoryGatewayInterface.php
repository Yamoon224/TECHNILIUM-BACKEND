<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Contracts;

use App\Modules\Catalog\Application\Data\OfferStockData;
use App\Modules\Catalog\Domain\Exceptions\InsufficientStockException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Catalog's public port for stock-level operations. The Inventory module
 * depends on this interface only - never on Catalog's Eloquent models - so
 * the two modules communicate through a contract, not a SQL join (cahier §9.1).
 */
interface InventoryGatewayInterface
{
    /** Null when the offer doesn't exist. */
    public function find(int $offerId): ?OfferStockData;

    /**
     * Atomically applies a stock delta - positive for stock received,
     * negative for stock removed. Row-locked to prevent races with
     * concurrent checkouts (cahier §7 "intégrité des stocks"). Returns the
     * new quantity.
     *
     * @throws ModelNotFoundException
     * @throws InsufficientStockException if the delta would go negative
     */
    public function adjustStock(int $offerId, int $delta): int;

    /**
     * @return list<OfferStockData>
     */
    public function listBelowThreshold(int $threshold): array;
}
