<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\AccessCatalogue;
use App\Modules\Payments\Application\Dashboard\PaymentsSection;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Payments\Infrastructure\Models\PaymentRefund;
use Carbon\CarbonImmutable;

it('identifies itself with a known ability', function () {
    $section = app(PaymentsSection::class);

    expect($section->key())->toBe('payments')
        ->and($section->ability())->toBe('manage-payments')
        ->and(AccessCatalogue::keys())->toContain($section->ability());
});

it('summarises collections, refunds, pending cash on delivery and failures', function () {
    $paid = Payment::factory()->completed()->create(['amount_amount' => 20_000, 'order_number' => 'P-1']);
    Payment::factory()->mobileMoney()->completed()->create(['amount_amount' => 30_000]);
    Payment::factory()->create(['amount_amount' => 7_000]); // cod pending
    Payment::factory()->failed()->create();
    PaymentRefund::create(['payment_id' => $paid->id, 'amount' => 4_000]);

    $section = app(PaymentsSection::class)->build(CarbonImmutable::now()->subDays(7)->startOfDay(), CarbonImmutable::now()->endOfDay());

    expect($section['link'])->toBe('/paiements');
    $kpis = collect($section['kpis'])->keyBy('key');
    expect($kpis['collected']['value'])->toBe(50_000)
        ->and($kpis['collected']['format'])->toBe('money')
        ->and($kpis['refunded']['value'])->toBe(4_000)
        ->and($kpis['awaiting']['value'])->toBe(7_000)
        ->and($kpis['failed']['value'])->toBe(1)
        ->and($kpis['failed']['tone'])->toBe('bad');

    $points = collect($section['charts'][0]['points'])->pluck('value', 'label_en');
    expect($section['charts'][0]['type'])->toBe('donut')
        ->and($points->all())->toBe(['Cash on delivery' => 20_000, 'Mobile money' => 30_000]);

    expect($section['tables'][0]['rows'])->toHaveCount(4);
});

it('copes with an empty database', function () {
    $section = app(PaymentsSection::class)->build(CarbonImmutable::now()->subDays(30), CarbonImmutable::now());

    expect(collect($section['kpis'])->pluck('value')->all())->toBe([0, 0, 0, 0])
        ->and($section['charts'][0]['points'])->toBe([])
        ->and($section['tables'][0]['rows'])->toBe([]);
});
