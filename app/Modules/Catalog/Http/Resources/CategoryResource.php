<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Domain\CatalogLocale;
use App\Modules\Catalog\Infrastructure\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Category $category */
        $category = $this->resource;
        $locale = CatalogLocale::resolve($request->query('lang'));

        return [
            'id' => $category->id,
            'name' => CatalogLocale::pick($locale, $category->name, $category->name_en),
            'slug' => $category->slug,
            'description' => $category->description,
            'children' => $this->whenLoaded('children', fn () => self::collection($category->children)),
        ];
    }
}
