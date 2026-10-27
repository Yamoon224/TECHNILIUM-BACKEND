<?php

declare(strict_types=1);

namespace App\Modules\Orders\Infrastructure\Factories;

use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $unitPrice = fake()->numberBetween(5_000, 500_000);
        $quantity = fake()->numberBetween(1, 3);

        return [
            'order_id' => Order::factory(),
            'offer_id' => fake()->numberBetween(1, 1000),
            'vendor_id' => null,
            'product_name' => fake()->words(3, true),
            'brand' => fake()->company(),
            'unit_price_amount' => $unitPrice,
            'currency' => 'XOF',
            'quantity' => $quantity,
            'line_total_amount' => $unitPrice * $quantity,
        ];
    }
}
