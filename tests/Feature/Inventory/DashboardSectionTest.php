<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Catalog\Infrastructure\Models\ProductVariant;
use App\Modules\Identity\Domain\AccessCatalogue;
use App\Modules\Inventory\Application\Dashboard\StockSection;
use App\Modules\Inventory\Infrastructure\Models\StockMovement;
use Carbon\CarbonImmutable;

it('declares its key and a known ability', function () {
    $section = app(StockSection::class);

    expect($section->key())->toBe('stock')
        ->and($section->ability())->toBe('manage-stock')
        ->and(AccessCatalogue::keys())->toContain($section->ability());
});

it('summarises stock levels and movements', function () {
    config(['reporting.low_stock_threshold' => 5]);

    $variant = ProductVariant::factory()->create(['sku' => 'SKU-LOW']);
    Offer::factory()->create(['product_variant_id' => $variant->id, 'stock_quantity' => 2]);
    Offer::factory()->outOfStock()->create();
    Offer::factory()->create(['stock_quantity' => 50]);

    StockMovement::factory()->create(['quantity_delta' => 10, 'quantity_after' => 10]);
    StockMovement::factory()->create(['quantity_delta' => 4, 'quantity_after' => 14]);
    StockMovement::factory()->create(['quantity_delta' => -3, 'quantity_after' => 11]);
    StockMovement::factory()->create(['quantity_delta' => 9, 'quantity_after' => 9, 'created_at' => now()->subYear()]);

    $data = app(StockSection::class)->build(CarbonImmutable::now()->subWeek(), CarbonImmutable::now()->addMinute());

    expect($data['link'])->toBe('/stock');

    $kpis = collect($data['kpis'])->keyBy('key');
    expect($kpis['out_of_stock']['value'])->toBe(1)
        ->and($kpis['out_of_stock']['tone'])->toBe('bad')
        ->and($kpis['low_stock']['value'])->toBe(1)
        ->and($kpis['low_stock']['tone'])->toBe('warn')
        ->and($kpis['movements']['value'])->toBe(3);

    $points = collect($data['charts'][0]['points'])->pluck('value', 'label');
    expect($data['charts'][0]['type'])->toBe('bar')
        ->and($points['Entrées'])->toBe(2)
        ->and($points['Sorties'])->toBe(1);

    $rows = $data['tables'][0]['rows'];
    expect($rows)->toHaveCount(2)
        ->and($rows[0][2])->toBe(0)
        ->and($rows[1][1])->toBe('SKU-LOW')
        ->and($rows[1][2])->toBe(2);
});

it('is robust on an empty database', function () {
    $data = app(StockSection::class)->build(CarbonImmutable::now()->subWeek(), CarbonImmutable::now());

    $kpis = collect($data['kpis'])->keyBy('key');
    expect($kpis['out_of_stock']['value'])->toBe(0)
        ->and($kpis['out_of_stock']['tone'])->toBeNull()
        ->and($kpis['movements']['value'])->toBe(0)
        ->and($data['tables'][0]['rows'])->toBe([]);
});
