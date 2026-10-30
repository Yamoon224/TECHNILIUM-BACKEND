<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\AccessCatalogue;
use App\Modules\Shipping\Application\Dashboard\ShipmentsSection;
use App\Modules\Shipping\Infrastructure\Models\Shipment;
use Carbon\CarbonImmutable;

it('identifies itself with a known ability', function () {
    $section = app(ShipmentsSection::class);

    expect($section->key())->toBe('shipments')
        ->and($section->ability())->toBe('manage-shipments')
        ->and(AccessCatalogue::keys())->toContain($section->ability());
});

it('counts parcels and lists the ones needing attention, oldest first', function () {
    Shipment::factory()->create(['order_number' => 'S-old', 'created_at' => now()->subDays(4)]);
    Shipment::factory()->create(['order_number' => 'S-assigned', 'assigned_to' => 3]);
    Shipment::factory()->inTransit()->create();
    Shipment::factory()->delivered()->create();
    Shipment::factory()->failedAttempt()->create(['order_number' => 'S-failed', 'assigned_to' => 3, 'created_at' => now()->subDays(1)]);

    $section = app(ShipmentsSection::class)->build(CarbonImmutable::now()->subDays(7)->startOfDay(), CarbonImmutable::now()->endOfDay());

    expect($section['link'])->toBe('/livraisons');
    $kpis = collect($section['kpis'])->keyBy('key');
    expect($kpis['to_assign']['value'])->toBe(1)
        ->and($kpis['in_transit']['value'])->toBe(1)
        ->and($kpis['delivered']['value'])->toBe(1)
        ->and($kpis['failed']['value'])->toBe(1)
        ->and($kpis['failed']['tone'])->toBe('bad');

    $points = collect($section['charts'][0]['points'])->pluck('value', 'label_en');
    expect($section['charts'][0]['type'])->toBe('bar')
        ->and($points['To dispatch'])->toBe(2)
        ->and($points['Failed attempt'])->toBe(1);

    $rows = $section['tables'][0]['rows'];
    expect($rows)->toHaveCount(2)
        ->and($rows[0][0])->toBe('S-old')
        ->and($rows[0][2])->toBe('Non assigné')
        ->and($rows[0][3])->toBe('4 j')
        ->and($rows[1][0])->toBe('S-failed')
        ->and($rows[1][2])->toBe('Livreur #3');
});

it('copes with an empty database', function () {
    $section = app(ShipmentsSection::class)->build(CarbonImmutable::now()->subDays(30), CarbonImmutable::now());

    expect(collect($section['kpis'])->pluck('value')->all())->toBe([0, 0, 0, 0])
        ->and($section['tables'][0]['rows'])->toBe([]);
});
