<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Domain\CatalogLocale;
use App\Modules\Catalog\Domain\OfferStatus;
use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Catalog\Infrastructure\Models\ProductVariant;
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
        $locale = CatalogLocale::resolve($request->query('lang'));

        return [
            'id' => $product->id,
            'brand' => $product->brand,
            'name' => CatalogLocale::pick($locale, $product->name, $product->name_en),
            'slug' => $product->slug,
            'description' => CatalogLocale::pick($locale, $product->description, $product->description_en),
            'image_url' => $product->relationLoaded('images') ? $product->imageUrl() : null,
            'images' => $this->whenLoaded('images', fn () => $product->images->map(fn ($image) => ['id' => $image->id, 'url' => $image->url()])->all()),
            'attributes' => $product->attributes,
            'condition_state' => $product->condition_state->value,
            'category' => $this->whenLoaded('category', fn () => new CategoryResource($product->category)),
            'best_offer' => $this->when(
                $product->relationLoaded('variants'),
                fn () => ($offer = $this->bestOffer($product)) ? new OfferResource($offer) : null,
            ),
            'variants' => $this->whenLoaded(
                'variants',
                fn () => ProductVariantResource::collection($product->variants),
            ),
        ];
    }

    private function bestOffer(Product $product): ?Offer
    {
        return $product->variants
            ->flatMap(fn (ProductVariant $variant) => $variant->offers)
            ->filter(fn (Offer $offer) => $offer->status === OfferStatus::Published)
            ->sortBy('price_amount')
            ->first();
    }
}
