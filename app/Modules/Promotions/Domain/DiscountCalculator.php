<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Domain;

/**
 * The one place a discount is computed (cahier §10.2: a calculation rule
 * exists in a single spot). Integer arithmetic, rounding half up, and the
 * result never exceeds the amount discounted — an order can reach 0 but
 * never go negative.
 */
final class DiscountCalculator
{
    public static function discount(CouponType $type, int $value, int $subtotal): int
    {
        if ($subtotal <= 0 || $value <= 0) {
            return 0;
        }

        $discount = match ($type) {
            CouponType::Percent => intdiv($subtotal * min($value, 100) + 50, 100),
            CouponType::Fixed => $value,
        };

        return min($discount, $subtotal);
    }
}
