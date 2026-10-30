<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Contracts;

use App\Modules\Catalog\Application\Data\CheckoutOfferData;
use App\Modules\Catalog\Domain\Exceptions\InsufficientStockException;

/**
 * Catalog's public port for the checkout flow. Orders depends on this
 * interface only - never on Catalog's Eloquent models - so the two
 * modules communicate through a contract, not a SQL join (cahier §9.1).
 */
interface CheckoutGatewayInterface
{
    /** Null when the offer doesn't exist or isn't published. */
    public function find(int $offerId): ?CheckoutOfferData;

    /**
     * Atomically decrements stock. Row-locked to prevent overselling under
     * concurrent checkouts (cahier §7 "intégrité des stocks").
     *
     * @throws InsufficientStockException
     */
    public function decrementStock(int $offerId, int $quantity): void;
}
