<?php

declare(strict_types=1);

namespace App\Modules\Payouts\Infrastructure\Factories;

use App\Modules\Payouts\Domain\PayoutStatus;
use App\Modules\Payouts\Infrastructure\Models\Payout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payout>
 */
class PayoutFactory extends Factory
{
    protected $model = Payout::class;

    public function definition(): array
    {
        return [
            'vendor_id' => fake()->numberBetween(1, 1000),
            'vendor_name' => fake()->company(),
            'status' => PayoutStatus::Pending,
            'currency' => 'XOF',
            'commission_basis_points' => 1000,
            'gross_amount' => 100_000,
            'commission_amount' => 10_000,
            'net_amount' => 90_000,
        ];
    }
}
