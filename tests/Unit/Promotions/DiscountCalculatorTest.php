<?php

declare(strict_types=1);

use App\Modules\Promotions\Domain\CouponType;
use App\Modules\Promotions\Domain\DiscountCalculator;

it('takes a percentage of the subtotal', function () {
    expect(DiscountCalculator::discount(CouponType::Percent, 10, 100_000))->toBe(10_000);
});

it('rounds a percentage half up to the minor unit', function () {
    // 10 % of 15 = 1.5 -> 2 ; 10 % of 14 = 1.4 -> 1
    expect(DiscountCalculator::discount(CouponType::Percent, 10, 15))->toBe(2)
        ->and(DiscountCalculator::discount(CouponType::Percent, 10, 14))->toBe(1);
});

it('takes a fixed amount', function () {
    expect(DiscountCalculator::discount(CouponType::Fixed, 5_000, 100_000))->toBe(5_000);
});

it('never discounts more than the subtotal, so an order cannot go negative', function (CouponType $type, int $value) {
    expect(DiscountCalculator::discount($type, $value, 8_000))->toBeLessThanOrEqual(8_000);
})->with([
    [CouponType::Fixed, 50_000],
    [CouponType::Percent, 100],
    [CouponType::Percent, 250], // a bad value is clamped to 100 %
]);

it('gives no discount on an empty subtotal or a zero value', function () {
    expect(DiscountCalculator::discount(CouponType::Percent, 10, 0))->toBe(0)
        ->and(DiscountCalculator::discount(CouponType::Fixed, 0, 10_000))->toBe(0)
        ->and(DiscountCalculator::discount(CouponType::Fixed, 5_000, -1))->toBe(0);
});
