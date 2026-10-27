<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Gateways;

use App\Modules\Catalog\Application\Contracts\ProductLookupInterface;
use App\Modules\Catalog\Domain\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Catalog\Infrastructure\Models\Product;

final class EloquentProductLookup implements ProductLookupInterface
{
    public function productIdsForOffers(array $offerIds): array
    {
        if ($offerIds === []) {
            return [];
        }

        return Offer::query()
            ->join('product_variants', 'product_variants.id', '=', 'offers.product_variant_id')
            ->whereIn('offers.id', $offerIds)
            ->pluck('product_variants.product_id', 'offers.id')
            ->map(fn ($productId) => (int) $productId)
            ->all();
    }

    public function isPublished(int $productId): bool
    {
        return Product::query()->where('id', $productId)->where('status', ProductStatus::Published)->exists();
    }
}
