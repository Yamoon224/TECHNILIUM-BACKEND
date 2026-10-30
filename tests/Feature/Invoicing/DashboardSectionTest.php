<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\AccessCatalogue;
use App\Modules\Invoicing\Application\Dashboard\InvoicesSection;
use App\Modules\Invoicing\Domain\InvoiceType;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use Carbon\CarbonImmutable;

function invoicingKpi(array $section, string $key): mixed
{
    return collect($section['kpis'])->firstWhere('key', $key)['value'];
}

it('declares a known ability and a key', function () {
    $section = app(InvoicesSection::class);

    expect($section->key())->toBe('invoices')
        ->and(AccessCatalogue::keys())->toContain($section->ability());
});

it('summarises the invoices and credit notes of the period', function () {
    $today = CarbonImmutable::now();

    Invoice::factory()->create(['issued_at' => $today, 'total_amount' => 118_000, 'customer_name' => 'Awa Diallo']);
    Invoice::factory()->create(['issued_at' => $today->subDays(2), 'total_amount' => 59_000]);
    Invoice::factory()->create([
        'type' => InvoiceType::CreditNote, 'number' => 'AV-2026-000001', 'issued_at' => $today, 'total_amount' => 20_000,
    ]);
    Invoice::factory()->create(['issued_at' => $today->subDays(60), 'total_amount' => 900_000]);

    $section = app(InvoicesSection::class)->build($today->subDays(6)->startOfDay(), $today->endOfDay());

    expect($section['title'])->toBe('Facturation')
        ->and($section['link'])->toBe('/factures')
        ->and(invoicingKpi($section, 'invoices_issued'))->toBe(2)
        ->and(invoicingKpi($section, 'invoiced_total'))->toBe(177_000)
        ->and(invoicingKpi($section, 'credit_notes'))->toBe(1)
        ->and(invoicingKpi($section, 'credit_notes_total'))->toBe(20_000);

    $points = $section['charts'][0]['points'];
    expect($points)->toHaveCount(7)
        ->and($points[6]['value'])->toBe(118_000)
        ->and($points[4]['value'])->toBe(59_000)
        ->and(array_sum(array_column($points, 'value')))->toBe(177_000);

    $rows = $section['tables'][0]['rows'];
    expect($rows)->toHaveCount(4)
        ->and(collect($rows)->firstWhere(2, 'Awa Diallo')[3])->toBe('118 000 XOF')
        ->and($rows[3][4])->toBe($today->subDays(60)->format('d/m/Y'));
});

it('groups the chart when the period is longer than 31 days', function () {
    $today = CarbonImmutable::now();

    $section = app(InvoicesSection::class)->build($today->subDays(89)->startOfDay(), $today->endOfDay());

    expect(count($section['charts'][0]['points']))->toBeLessThanOrEqual(31);
});

it('copes with an empty database', function () {
    $today = CarbonImmutable::now();

    $section = app(InvoicesSection::class)->build($today->subDays(6)->startOfDay(), $today->endOfDay());

    expect(invoicingKpi($section, 'invoices_issued'))->toBe(0)
        ->and(invoicingKpi($section, 'invoiced_total'))->toBe(0)
        ->and($section['tables'][0]['rows'])->toBe([])
        ->and($section['charts'][0]['points'])->toHaveCount(7);
});
