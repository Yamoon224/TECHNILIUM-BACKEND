<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Infrastructure\Factories;

use App\Modules\Promotions\Domain\CouponType;
use App\Modules\Promotions\Infrastructure\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('PROMO-####')),
            'type' => CouponType::Percent,
            'value' => 10,
            'min_order_amount' => 0,
            'used_count' => 0,
            'active' => true,
        ];
    }

    public function fixed(int $amount): static
    {
        return $this->state(fn () => ['type' => CouponType::Fixed, 'value' => $amount]);
    }
}
