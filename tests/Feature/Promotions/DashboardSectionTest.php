<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\AccessCatalogue;
use App\Modules\Promotions\Application\Dashboard\PromotionsSection;
use App\Modules\Promotions\Infrastructure\Models\Announcement;
use App\Modules\Promotions\Infrastructure\Models\Coupon;
use App\Modules\Promotions\Infrastructure\Models\CouponRedemption;
use Carbon\CarbonImmutable;

it('declares its key and a known ability', function () {
    $section = app(PromotionsSection::class);

    expect($section->key())->toBe('promotions')
        ->and($section->ability())->toBe('manage-promotions')
        ->and(AccessCatalogue::keys())->toContain($section->ability());
});

it('summarises coupons and announcements', function () {
    Coupon::factory()->create(['code' => 'LATER', 'ends_at' => now()->addDays(20)]);
    Coupon::factory()->create(['code' => 'SOON', 'ends_at' => now()->addDays(2), 'max_uses' => 10, 'used_count' => 3]);
    Coupon::factory()->create(['code' => 'OPEN']); // no end date
    Coupon::factory()->create(['code' => 'EXHAUSTED', 'max_uses' => 5, 'used_count' => 5]);
    Coupon::factory()->create(['code' => 'EXPIRED', 'ends_at' => now()->subDay()]);
    Coupon::factory()->create(['code' => 'OFF', 'active' => false]);
    Coupon::factory()->create(['code' => 'FUTURE', 'starts_at' => now()->addDay()]);

    $coupon = Coupon::query()->where('code', 'SOON')->first();
    CouponRedemption::create(['coupon_id' => $coupon->id, 'order_number' => 'O-1', 'customer_key' => '0700', 'discount_amount' => 500]);
    CouponRedemption::create(['coupon_id' => $coupon->id, 'order_number' => 'O-2', 'customer_key' => '0701', 'discount_amount' => 500]);

    Announcement::create(['title' => 'Now', 'path' => 'a.jpg', 'is_active' => true]);
    Announcement::create(['title' => 'Soon', 'path' => 'b.jpg', 'is_active' => true, 'starts_at' => now()->addDays(3)]);
    Announcement::create(['title' => 'Old', 'path' => 'c.jpg', 'is_active' => true, 'archived_at' => now()]);

    $data = app(PromotionsSection::class)->build(CarbonImmutable::now()->subWeek(), CarbonImmutable::now()->addMinute());

    expect($data['link'])->toBe('/promotions');

    $kpis = collect($data['kpis'])->keyBy('key');
    expect($kpis['coupons_active']['value'])->toBe(3)
        ->and($kpis['coupon_redemptions']['value'])->toBe(2)
        ->and($kpis['announcements_showing']['value'])->toBe(1)
        ->and($kpis['announcements_scheduled']['value'])->toBe(1);

    $rows = $data['tables'][0]['rows'];
    expect($rows)->toHaveCount(2)
        ->and($rows[0][0])->toBe('SOON')
        ->and($rows[0][1])->toBe('10 %')
        ->and($rows[0][3])->toBe('3 / 10')
        ->and($rows[1][0])->toBe('LATER');
});

it('is robust on an empty database', function () {
    $data = app(PromotionsSection::class)->build(CarbonImmutable::now()->subWeek(), CarbonImmutable::now());

    $kpis = collect($data['kpis'])->keyBy('key');
    expect($kpis['coupons_active']['value'])->toBe(0)
        ->and($kpis['announcements_showing']['value'])->toBe(0)
        ->and($data['tables'][0]['rows'])->toBe([]);
});
