<?php

declare(strict_types=1);

namespace App\Modules\Customers\Infrastructure\Factories;

use App\Modules\Customers\Domain\ReviewStatus;
use App\Modules\Customers\Infrastructure\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'product_id' => fake()->numberBetween(1, 1000),
            'customer_id' => fake()->numberBetween(1, 1000),
            'order_number' => 'TCH-'.fake()->numerify('########'),
            'rating' => 5,
            'title' => fake()->sentence(3),
            'body' => fake()->sentence(8),
            'author_name' => 'Awa D.',
            'status' => ReviewStatus::Published,
            'published_at' => now(),
        ];
    }

    public function status(ReviewStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'published_at' => $status === ReviewStatus::Published ? now() : null,
        ]);
    }
}
