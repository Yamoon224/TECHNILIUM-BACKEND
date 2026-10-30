<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\AccessCatalogue;
use App\Modules\Orders\Application\Dashboard\OrdersSection;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use Carbon\CarbonImmutable;

it('identifies itself with a known ability', function () {
    $section = app(OrdersSection::class);

    expect($section->key())->toBe('orders')
        ->and($section->ability())->toBe('manage-orders')
        ->and(AccessCatalogue::keys())->toContain($section->ability());
});

it('summarises orders, backlog and latest orders', function () {
    Order::factory()->create(['order_number' => 'TCH-1', 'customer_name' => 'Awa', 'subtotal_amount' => 10_000, 'created_at' => now()]);
    Order::factory()->create(['order_number' => 'TCH-2', 'status' => OrderStatus::Confirmed, 'created_at' => now()->subDays(5)]);
    Order::factory()->create(['order_number' => 'TCH-3', 'status' => OrderStatus::Delivered, 'created_at' => now()]);
    Order::factory()->create(['order_number' => 'TCH-4', 'status' => OrderStatus::Cancelled, 'created_at' => now()->subDays(40)]);

    $section = app(OrdersSection::class)->build(CarbonImmutable::now()->subDays(30)->startOfDay(), CarbonImmutable::now()->endOfDay());

    expect($section['title'])->toBe('Commandes')
        ->and($section['link'])->toBe('/commandes');

    $kpis = collect($section['kpis'])->keyBy('key');
    expect($kpis['period']['value'])->toBe(3)
        ->and($kpis['today']['value'])->toBe(2)
        ->and($kpis['to_process']['value'])->toBe(2)
        ->and($kpis['stalled']['value'])->toBe(1)
        ->and($kpis['stalled']['tone'])->toBe('bad');

    $points = collect($section['charts'][0]['points'])->pluck('value', 'label_en');
    expect($section['charts'][0]['type'])->toBe('donut')
        ->and($points->all())->toBe(['New' => 1, 'Confirmed' => 1, 'Delivered' => 1]);

    $rows = $section['tables'][0]['rows'];
    expect($rows)->toHaveCount(4)
        ->and(collect($rows)->pluck(0))->toContain('TCH-1');
});

it('copes with an empty database', function () {
    $section = app(OrdersSection::class)->build(CarbonImmutable::now()->subDays(30), CarbonImmutable::now());

    expect(collect($section['kpis'])->pluck('value')->all())->toBe([0, 0, 0, 0])
        ->and($section['charts'][0]['points'])->toBe([])
        ->and($section['tables'][0]['rows'])->toBe([]);
});
