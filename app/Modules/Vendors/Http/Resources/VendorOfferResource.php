<?php

declare(strict_types=1);

namespace App\Modules\Vendors\Http\Resources;

use App\Modules\Catalog\Application\Data\VendorOfferData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps Catalog's public VendorOfferData DTO - never its Eloquent Offer
 * model (cahier §9.1).
 */
class VendorOfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var VendorOfferData $offer */
        $offer = $this->resource;

        return [
            'id' => $offer->offerId,
            'variant_id' => $offer->variantId,
            'product_name' => $offer->productName,
            'brand' => $offer->brand,
            'sku' => $offer->sku,
            'price_amount' => $offer->priceAmount,
            'currency' => $offer->currency,
            'stock_quantity' => $offer->stockQuantity,
            'warranty_months' => $offer->warrantyMonths,
            'status' => $offer->status,
        ];
    }
}
