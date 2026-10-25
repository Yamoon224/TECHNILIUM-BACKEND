<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Factories;

use App\Modules\Catalog\Domain\OfferStatus;
use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Catalog\Infrastructure\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    protected $model = Offer::class;

    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'vendor_id' => null,
            'price_amount' => fake()->numberBetween(5_000, 2_000_000),
            'currency' => 'XOF',
            'compare_at_price_amount' => null,
            'condition_grade' => null,
            'warranty_months' => fake()->randomElement([0, 6, 12, 24]),
            'status' => OfferStatus::Published,
            'published_at' => now(),
        ];
    }

    public function fromVendor(int $vendorId): static
    {
        return $this->state(fn () => ['vendor_id' => $vendorId]);
    }

    public function pendingReview(): static
    {
        return $this->state(fn () => ['status' => OfferStatus::PendingReview, 'published_at' => null]);
    }
}
