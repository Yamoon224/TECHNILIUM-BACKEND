<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Factories;

use App\Modules\Catalog\Infrastructure\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'parent_id' => null,
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'position' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }
}
