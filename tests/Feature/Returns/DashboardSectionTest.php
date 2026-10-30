<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\AccessCatalogue;
use App\Modules\Returns\Application\Dashboard\ReturnsSection;
use App\Modules\Returns\Domain\ReturnStatus;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use Carbon\CarbonImmutable;

it('identifies itself with a known ability', function () {
    $section = app(ReturnsSection::class);

    expect($section->key())->toBe('returns')
        ->and($section->ability())->toBe('manage-returns')
        ->and(AccessCatalogue::keys())->toContain($section->ability());
});

it('summarises open and refunded returns', function () {
    ReturnRequest::factory()->create(['order_number' => 'R-old', 'created_at' => now()->subDays(3)]);
    ReturnRequest::factory()->status(ReturnStatus::Accepted)->create();
    ReturnRequest::factory()->status(ReturnStatus::Received)->create();
    ReturnRequest::factory()->status(ReturnStatus::Refunded)->create(['refunded_amount' => 8_000, 'refunded_at' => now()]);
    ReturnRequest::factory()->status(ReturnStatus::Rejected)->create();

    $section = app(ReturnsSection::class)->build(CarbonImmutable::now()->subDays(7)->startOfDay(), CarbonImmutable::now()->endOfDay());

    expect($section['link'])->toBe('/retours');
    $kpis = collect($section['kpis'])->keyBy('key');
    expect($kpis['open']['value'])->toBe(3)
        ->and($kpis['to_decide']['value'])->toBe(1)
        ->and($kpis['refunded_count']['value'])->toBe(1)
        ->and($kpis['refunded_amount']['value'])->toBe(8_000)
        ->and($kpis['refunded_amount']['format'])->toBe('money');

    expect($section['charts'][0]['type'])->toBe('donut')
        ->and(collect($section['charts'][0]['points'])->sum('value'))->toBe(5);

    $rows = $section['tables'][0]['rows'];
    expect($rows)->toHaveCount(3)
        ->and($rows[0][1])->toBe('R-old');
});

it('copes with an empty database', function () {
    $section = app(ReturnsSection::class)->build(CarbonImmutable::now()->subDays(30), CarbonImmutable::now());

    expect(collect($section['kpis'])->pluck('value')->all())->toBe([0, 0, 0, 0])
        ->and($section['charts'][0]['points'])->toBe([])
        ->and($section['tables'][0]['rows'])->toBe([]);
});
