<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Data;

/**
 * Catalog's public, read-only view of an offer's stock level - the only
 * shape the Inventory module is allowed to depend on. Never expose
 * Infrastructure\Models\Offer outside this module (cahier §9.1).
 */
final readonly class OfferStockData
{
    public function __construct(
        public int $offerId,
        public string $productName,
        public string $brand,
        public string $sku,
        public int $stockQuantity,
        public ?int $vendorId,
    ) {}
}
