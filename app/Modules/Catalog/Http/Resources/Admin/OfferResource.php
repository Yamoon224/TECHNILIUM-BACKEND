<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources\Admin;

use App\Modules\Catalog\Infrastructure\Models\Offer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Offer $offer */
        $offer = $this->resource;

        return [
            'id' => $offer->id,
            'vendor_id' => $offer->vendor_id,
            'product_name' => $offer->variant->product->name,
            'brand' => $offer->variant->product->brand,
            'sku' => $offer->variant->sku,
            'price_amount' => $offer->price_amount,
            'currency' => $offer->currency,
            'stock_quantity' => $offer->stock_quantity,
            'warranty_months' => $offer->warranty_months,
            'status' => $offer->status->value,
            'created_at' => $offer->created_at?->toIso8601String(),
        ];
    }
}
