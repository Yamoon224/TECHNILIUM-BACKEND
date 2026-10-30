<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\AccessCatalogue;
use App\Modules\Vendors\Application\Dashboard\VendorsSection;
use App\Modules\Vendors\Infrastructure\Models\Vendor;
use Carbon\CarbonImmutable;

it('declares its key and a known ability', function () {
    $section = app(VendorsSection::class);

    expect($section->key())->toBe('vendors')
        ->and($section->ability())->toBe('manage-vendors')
        ->and(AccessCatalogue::keys())->toContain($section->ability());
});

it('summarises vendors by status', function () {
    Vendor::factory()->active()->count(2)->create();
    Vendor::factory()->create(['company_name' => 'Nouveau SARL', 'created_at' => now()->addMinute()]);
    Vendor::factory()->suspended()->create();

    $data = app(VendorsSection::class)->build(CarbonImmutable::now()->subMonth(), CarbonImmutable::now());

    expect($data['link'])->toBe('/vendeurs');

    $kpis = collect($data['kpis'])->keyBy('key');
    expect($kpis['vendors_active']['value'])->toBe(2)
        ->and($kpis['vendors_pending']['value'])->toBe(1)
        ->and($kpis['vendors_pending']['tone'])->toBe('warn')
        ->and($kpis['vendors_suspended']['value'])->toBe(1);

    $points = collect($data['charts'][0]['points'])->pluck('value', 'label');
    expect($data['charts'][0]['type'])->toBe('donut')
        ->and($points['Actifs'])->toBe(2)
        ->and($points['Suspendus'])->toBe(1);

    $rows = $data['tables'][0]['rows'];
    expect($rows)->toHaveCount(4)
        ->and($rows[0][0])->toBe('Nouveau SARL')
        ->and($rows[0][1])->toBe('En attente');
});

it('lists at most 8 vendors', function () {
    Vendor::factory()->count(10)->create();

    $data = app(VendorsSection::class)->build(CarbonImmutable::now()->subMonth(), CarbonImmutable::now());

    expect($data['tables'][0]['rows'])->toHaveCount(8);
});

it('is robust on an empty database', function () {
    $data = app(VendorsSection::class)->build(CarbonImmutable::now()->subMonth(), CarbonImmutable::now());

    $kpis = collect($data['kpis'])->keyBy('key');
    expect($kpis['vendors_active']['value'])->toBe(0)
        ->and($kpis['vendors_pending']['tone'])->toBeNull()
        ->and($data['tables'][0]['rows'])->toBe([]);
});
