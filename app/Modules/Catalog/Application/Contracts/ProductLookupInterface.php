<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Contracts;

/**
 * Catalog's public port for modules that talk about products by id without
 * owning them (reviews). Never expose the Eloquent models (cahier §9.1).
 */
interface ProductLookupInterface
{
    /**
     * Maps each given offer id to the product it sells; unknown offers are absent.
     *
     * @param  list<int>  $offerIds
     * @return array<int, int>  offer id => product id
     */
    public function productIdsForOffers(array $offerIds): array;

    /** True when the product exists and is published (visible in the storefront). */
    public function isPublished(int $productId): bool;
}
