<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Data;

/**
 * Catalog's public, read-only view of an offer for the Vendors module's
 * self-service listing management — the only shape Vendors is allowed to
 * depend on. Never expose Infrastructure\Models\Offer outside this module
 * (cahier §9.1).
 */
final readonly class VendorOfferData
{
    public function __construct(
        public int $offerId,
        public int $variantId,
        public int $vendorId,
        public string $productName,
        public string $brand,
        public string $sku,
        public int $priceAmount,
        public string $currency,
        public int $stockQuantity,
        public int $warrantyMonths,
        public string $status,
    ) {}
}
