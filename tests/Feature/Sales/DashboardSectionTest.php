<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\AccessCatalogue;
use App\Modules\Sales\Application\Dashboard\InternalSalesSection;
use App\Modules\Sales\Infrastructure\Models\CashClosing;
use App\Modules\Sales\Infrastructure\Models\InternalSale;
use Carbon\CarbonImmutable;

function dashboardSale(string $number, int $seller, int $amount, $at, array $extra = []): InternalSale
{
    $sale = InternalSale::create(array_merge([
        'order_number' => $number, 'seller_id' => $seller, 'payment_id' => 1, 'method' => 'cash', 'amount' => $amount, 'currency' => 'XOF',
    ], $extra));
    $sale->forceFill(['created_at' => $at, 'updated_at' => $at])->save();

    return $sale;
}

it('identifies itself with a known ability', function () {
    $section = app(InternalSalesSection::class);

    expect($section->key())->toBe('internal-sales')
        ->and($section->ability())->toBe('manage-internal-sales')
        ->and(AccessCatalogue::keys())->toContain($section->ability());
});

it('totals counter sales and ranks cashiers', function () {
    dashboardSale('S-1', 7, 10_000, now());
    dashboardSale('S-2', 7, 5_000, now()->subDays(2));
    dashboardSale('S-3', 8, 3_000, now());
    dashboardSale('S-4', 8, 99_000, now(), ['cancelled_at' => now()]);
    dashboardSale('S-5', 8, 50_000, now()->subDays(60));
    CashClosing::create(['user_id' => 7, 'user_name' => 'Fatou', 'closed_at' => now()]);

    $section = app(InternalSalesSection::class)->build(CarbonImmutable::now()->subDays(6)->startOfDay(), CarbonImmutable::now()->endOfDay());

    expect($section['link'])->toBe('/ventes-internes');
    $kpis = collect($section['kpis'])->pluck('value', 'key');
    expect($kpis->all())->toBe(['period_count' => 3, 'period_amount' => 18_000, 'today_count' => 2, 'today_amount' => 13_000]);

    $points = $section['charts'][0]['points'];
    expect($section['charts'][0]['type'])->toBe('bar')
        ->and($points)->toHaveCount(7)
        ->and($points[6]['value'])->toBe(13_000)
        ->and($points[4]['value'])->toBe(5_000);

    expect($section['tables'][0]['rows'][0])->toBe(['Fatou', 2, '15 000 XOF'])
        ->and($section['tables'][0]['rows'][1][0])->toBe('Caissier #8');
});

it('groups the chart when the period exceeds 31 days', function () {
    $section = app(InternalSalesSection::class)->build(CarbonImmutable::now()->subDays(99)->startOfDay(), CarbonImmutable::now()->endOfDay());

    expect(count($section['charts'][0]['points']))->toBeLessThanOrEqual(31);
});

it('copes with an empty database', function () {
    $section = app(InternalSalesSection::class)->build(CarbonImmutable::now()->subDays(30), CarbonImmutable::now());

    expect(collect($section['kpis'])->pluck('value')->all())->toBe([0, 0, 0, 0])
        ->and($section['tables'][0]['rows'])->toBe([]);
});
