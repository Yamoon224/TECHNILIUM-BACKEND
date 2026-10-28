<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources\Admin;

use App\Modules\Catalog\Infrastructure\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;
        $variant = $product->variants->first();
        $offer = $variant?->offers->first();

        return [
            'id' => $product->id,
            'category_id' => $product->category_id,
            'brand' => $product->brand,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'image_url' => $product->relationLoaded('images') ? $product->imageUrl() : null,
            'images' => $this->whenLoaded('images', fn () => $product->images->map(fn ($image) => ['id' => $image->id, 'url' => $image->url()])->all()),
            'condition_state' => $product->condition_state->value,
            'status' => $product->status->value,
            'sku' => $variant?->sku,
            'price_amount' => $offer?->price_amount,
            'currency' => $offer?->currency,
            'stock_quantity' => $offer?->stock_quantity,
            'warranty_months' => $offer?->warranty_months,
        ];
    }
}
