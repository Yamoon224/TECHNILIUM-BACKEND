<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Factories;

use App\Modules\Catalog\Domain\ConditionState;
use App\Modules\Catalog\Domain\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\Category;
use App\Modules\Catalog\Infrastructure\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $brand = fake()->randomElement(['Samsung', 'Apple', 'Xiaomi', 'Tecno', 'Infinix', 'HP', 'Dell']);
        $name = $brand.' '.fake()->bothify('Model ##?').' '.fake()->word();

        return [
            'category_id' => Category::factory(),
            'brand' => $brand,
            'name' => $name,
            'slug' => Str::slug($name.'-'.fake()->unique()->numberBetween(1000, 999999)),
            'description' => fake()->paragraph(),
            'attributes' => [
                'ram_gb' => fake()->randomElement([4, 6, 8, 12, 16]),
                'storage_gb' => fake()->randomElement([64, 128, 256, 512]),
            ],
            'condition_state' => ConditionState::Neuf,
            'status' => ProductStatus::Published,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => ProductStatus::Draft]);
    }
}
