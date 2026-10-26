<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Factories;

use App\Modules\Payments\Domain\PaymentMethod;
use App\Modules\Payments\Domain\PaymentStatus;
use App\Modules\Payments\Infrastructure\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_number' => 'TCH-'.fake()->unique()->numerify('########'),
            'method' => PaymentMethod::Cod,
            'status' => PaymentStatus::Pending,
            'amount_amount' => fake()->numberBetween(5_000, 500_000),
            'currency' => 'XOF',
            'provider' => null,
            'provider_reference' => null,
            'created_by' => null,
            'paid_at' => null,
        ];
    }

    public function mobileMoney(): static
    {
        return $this->state(fn () => [
            'method' => PaymentMethod::MobileMoney,
            'provider' => fake()->randomElement(['orange_money', 'mtn_money', 'wave']),
            'provider_reference' => fake()->bothify('MM-########'),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::Completed, 'paid_at' => now()]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::Failed]);
    }
}
