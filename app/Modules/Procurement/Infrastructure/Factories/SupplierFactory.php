<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Infrastructure\Factories;

use App\Modules\Procurement\Infrastructure\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'contact_name' => fake()->name(),
            'phone' => fake()->numerify('07########'),
            'payment_terms_days' => 30,
            'active' => true,
        ];
    }
}
