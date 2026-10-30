<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Resources;

use App\Modules\Catalog\Application\Data\OfferStockData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps Catalog's public OfferStockData DTO - never its Eloquent Offer
 * model (cahier §9.1).
 */
class OfferStockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OfferStockData $offer */
        $offer = $this->resource;

        return [
            'offer_id' => $offer->offerId,
            'product_name' => $offer->productName,
            'brand' => $offer->brand,
            'sku' => $offer->sku,
            'stock_quantity' => $offer->stockQuantity,
            'vendor_id' => $offer->vendorId,
        ];
    }
}
