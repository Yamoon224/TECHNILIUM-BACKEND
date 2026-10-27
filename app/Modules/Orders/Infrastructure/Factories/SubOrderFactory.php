<?php

declare(strict_types=1);

namespace App\Modules\Orders\Infrastructure\Factories;

use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\SubOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubOrder>
 */
class SubOrderFactory extends Factory
{
    protected $model = SubOrder::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'vendor_id' => null,
            'status' => OrderStatus::Pending,
            'subtotal_amount' => 50_000,
            'currency' => 'XOF',
        ];
    }
}
