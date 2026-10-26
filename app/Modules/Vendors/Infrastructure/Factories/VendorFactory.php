<?php

declare(strict_types=1);

namespace App\Modules\Vendors\Infrastructure\Factories;

use App\Modules\Vendors\Domain\VendorStatus;
use App\Modules\Vendors\Infrastructure\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    public function definition(): array
    {
        return [
            'user_id' => fake()->unique()->numberBetween(1, 1_000_000),
            'company_name' => fake()->company(),
            'phone' => fake()->numerify('07########'),
            'address' => fake()->address(),
            'status' => VendorStatus::Pending,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => VendorStatus::Active]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => VendorStatus::Suspended]);
    }
}
