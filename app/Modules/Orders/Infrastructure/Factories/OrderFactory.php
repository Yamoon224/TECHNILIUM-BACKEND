<?php

declare(strict_types=1);

namespace App\Modules\Orders\Infrastructure\Factories;

use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'order_number' => 'TCH-'.fake()->unique()->numerify('########'),
            'customer_id' => null,
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->numerify('07########'),
            'delivery_address' => fake()->streetAddress(),
            'delivery_city' => fake()->city(),
            'notes' => null,
            'payment_method' => 'cod',
            'status' => OrderStatus::Pending,
            'subtotal_amount' => fake()->numberBetween(5_000, 1_000_000),
            'currency' => 'XOF',
        ];
    }
}
