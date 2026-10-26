<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Infrastructure\Factories;

use App\Modules\Shipping\Domain\ShipmentStatus;
use App\Modules\Shipping\Infrastructure\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return [
            'order_number' => 'TCH-'.fake()->unique()->numerify('########'),
            'assigned_to' => null,
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->numerify('07########'),
            'delivery_address' => fake()->address(),
            'delivery_city' => 'Abidjan',
            'status' => ShipmentStatus::Pending,
            'attempts' => 0,
            'notes' => null,
            'delivered_at' => null,
        ];
    }

    public function inTransit(): static
    {
        return $this->state(fn () => ['status' => ShipmentStatus::InTransit]);
    }

    public function delivered(): static
    {
        return $this->state(fn () => ['status' => ShipmentStatus::Delivered, 'delivered_at' => now()]);
    }

    public function failedAttempt(): static
    {
        return $this->state(fn () => ['status' => ShipmentStatus::FailedAttempt, 'attempts' => 1]);
    }
}
