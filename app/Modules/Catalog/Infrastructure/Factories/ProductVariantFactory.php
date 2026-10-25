<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Factories;

use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Catalog\Infrastructure\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####-???')),
            'ean' => fake()->unique()->ean13(),
            'attributes' => [
                'color' => fake()->safeColorName(),
            ],
        ];
    }
}
