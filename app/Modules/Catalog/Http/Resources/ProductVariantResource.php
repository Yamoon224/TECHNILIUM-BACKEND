<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Infrastructure\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProductVariant $variant */
        $variant = $this->resource;

        return [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'ean' => $variant->ean,
            'attributes' => $variant->attributes,
            'offers' => $this->whenLoaded('offers', fn () => OfferResource::collection($variant->offers)),
        ];
    }
}
