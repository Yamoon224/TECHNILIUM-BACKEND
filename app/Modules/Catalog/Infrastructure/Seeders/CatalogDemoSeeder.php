<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Seeders;

use App\Modules\Catalog\Domain\OfferStatus;
use App\Modules\Catalog\Domain\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\Category;
use App\Modules\Catalog\Infrastructure\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Minimal demo catalog so a fresh `php artisan migrate --seed` has something
 * browsable. Not a substitute for the real import pipeline (cahier §6.1).
 */
class CatalogDemoSeeder extends Seeder
{
    public function run(): void
    {
        $smartphones = Category::query()->firstOrCreate(
            ['slug' => 'smartphones'],
            ['name' => 'Smartphones', 'position' => 1],
        );

        $laptops = Category::query()->firstOrCreate(
            ['slug' => 'ordinateurs'],
            ['name' => 'Ordinateurs', 'position' => 2],
        );

        $this->productWithOffer($smartphones, 'Samsung', 'Galaxy A55', 185_000, 14);
        $this->productWithOffer($smartphones, 'Apple', 'iPhone 15', 650_000, 6);
        $this->productWithOffer($laptops, 'HP', 'Pavilion 15', 420_000, 0);
    }

    private function productWithOffer(Category $category, string $brand, string $model, int $priceXof, int $stock): void
    {
        $product = Product::query()->firstOrCreate(
            ['slug' => str($brand.'-'.$model)->slug()],
            [
                'category_id' => $category->id,
                'brand' => $brand,
                'name' => "{$brand} {$model}",
                'description' => "{$brand} {$model} — produit de démonstration.",
                'status' => ProductStatus::Published,
            ],
        );

        if ($product->variants()->exists()) {
            return;
        }

        $variant = $product->variants()->create([
            'sku' => str($brand.'-'.$model.'-base')->upper()->slug('-'),
            'attributes' => ['color' => 'Noir'],
        ]);

        $variant->offers()->create([
            'vendor_id' => null,
            'price_amount' => $priceXof,
            'currency' => 'XOF',
            'warranty_months' => 12,
            'stock_quantity' => $stock,
            'status' => OfferStatus::Published,
            'published_at' => now(),
        ]);
    }
}
