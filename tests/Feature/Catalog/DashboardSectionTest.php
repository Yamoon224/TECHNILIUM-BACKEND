<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Dashboard\CatalogSection;
use App\Modules\Catalog\Infrastructure\Models\Banner;
use App\Modules\Catalog\Infrastructure\Models\Category;
use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Catalog\Infrastructure\Models\ProductImage;
use App\Modules\Catalog\Infrastructure\Models\ProductVariant;
use App\Modules\Identity\Domain\AccessCatalogue;
use Carbon\CarbonImmutable;

it('declares its key and a known ability', function () {
    $section = app(CatalogSection::class);

    expect($section->key())->toBe('catalog')
        ->and($section->ability())->toBe('manage-catalog')
        ->and(AccessCatalogue::keys())->toContain($section->ability());
});

it('summarises the catalogue', function () {
    $withPhoto = Product::factory()->create();
    ProductImage::create(['product_id' => $withPhoto->id, 'path' => 'a.jpg', 'position' => 0]);
    $noPhoto = Product::factory()->create(['brand' => 'Tecno', 'name' => 'Spark']);
    Product::factory()->draft()->create();
    Product::factory()->create(['status' => 'archived']);
    Category::factory()->create(['is_active' => false]);
    Category::factory()->create(['is_active' => true]);

    $variant = ProductVariant::factory()->create(['product_id' => $noPhoto->id]);
    Offer::factory()->pendingReview()->create(['product_variant_id' => $variant->id, 'price_amount' => 150000, 'created_at' => now()->subDays(3)]);
    Offer::factory()->pendingReview()->fromVendor(7)->create(['product_variant_id' => $variant->id, 'price_amount' => 90000, 'created_at' => now()->subDays(5)]);
    Offer::factory()->create();

    Banner::create(['placement' => 'hero', 'path' => 'b.jpg', 'is_active' => true, 'position' => 0]);
    Banner::create(['placement' => 'hero', 'path' => 'c.jpg', 'is_active' => false, 'position' => 1]);

    $data = app(CatalogSection::class)->build(CarbonImmutable::now()->subMonth(), CarbonImmutable::now());

    expect($data['title'])->toBe('Catalogue')
        ->and($data['title_en'])->toBe('Catalogue')
        ->and($data['link'])->toBe('/produits');

    $kpis = collect($data['kpis'])->keyBy('key');
    expect($kpis['products_published']['value'])->toBe(3) // 2 explicit + the product created by the last Offer factory
        ->and($kpis['products_draft']['value'])->toBe(1)
        ->and($kpis['products_archived']['value'])->toBe(1)
        ->and($kpis['offers_pending']['value'])->toBe(2)
        ->and($kpis['offers_pending']['tone'])->toBe('warn')
        ->and($kpis['products_no_photo']['tone'])->toBe('warn')
        ->and($kpis['categories_active']['value'])->toBeGreaterThanOrEqual(1)
        ->and($kpis['banners_active']['value'])->toBe(1);

    $points = collect($data['charts'][0]['points'])->pluck('value', 'label');
    expect($data['charts'][0]['type'])->toBe('donut')
        ->and($points['Brouillons'])->toBe(1)
        ->and($points['Archivés'])->toBe(1);

    $rows = $data['tables'][0]['rows'];
    expect($rows)->toHaveCount(2)
        ->and($rows[0][1])->toBe('Vendeur #7')
        ->and($rows[0][2])->toBe(90000)
        ->and($rows[1][1])->toBe('Technilium');
});

it('is robust on an empty database', function () {
    $data = app(CatalogSection::class)->build(CarbonImmutable::now()->subMonth(), CarbonImmutable::now());

    $kpis = collect($data['kpis'])->keyBy('key');
    expect($kpis['products_published']['value'])->toBe(0)
        ->and($kpis['offers_pending']['tone'])->toBeNull()
        ->and($data['tables'][0]['rows'])->toBe([])
        ->and(collect($data['charts'][0]['points'])->sum('value'))->toBe(0);
});
