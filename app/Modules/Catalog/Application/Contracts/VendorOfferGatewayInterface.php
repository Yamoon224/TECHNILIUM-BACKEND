<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Contracts;

use App\Modules\Catalog\Application\Data\VendorOfferData;

/**
 * Catalog's public port for vendor self-service listing management. The
 * Vendors module depends on this interface only — never on Catalog's
 * Eloquent models — so the two modules communicate through a contract, not
 * a SQL join (cahier §9.1).
 */
interface VendorOfferGatewayInterface
{
    public function variantExists(int $variantId): bool;

    /**
     * @param  array{price_amount: int, currency?: string, stock_quantity: int, warranty_months?: int, condition_grade?: string|null}  $data
     */
    public function createOffer(int $variantId, int $vendorId, array $data): VendorOfferData;

    /** Null when the offer doesn't exist or doesn't belong to this vendor. */
    public function findOwnedByVendor(int $offerId, int $vendorId): ?VendorOfferData;

    /**
     * Null when the offer doesn't exist, doesn't belong to this vendor, or
     * is archived (a vendor edits an active listing, not a dead one).
     *
     * @param  array{price_amount?: int, stock_quantity?: int, warranty_months?: int}  $data
     */
    public function updateOffer(int $offerId, int $vendorId, array $data): ?VendorOfferData;

    /** False when the offer doesn't exist or doesn't belong to this vendor. */
    public function archiveOffer(int $offerId, int $vendorId): bool;

    /**
     * @return list<VendorOfferData>
     */
    public function listOffersForVendor(int $vendorId): array;
}
