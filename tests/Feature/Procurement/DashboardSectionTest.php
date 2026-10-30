<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\AccessCatalogue;
use App\Modules\Procurement\Application\Dashboard\ProcurementSection;
use App\Modules\Procurement\Domain\PurchaseOrderStatus;
use App\Modules\Procurement\Infrastructure\Models\PurchaseOrder;
use App\Modules\Procurement\Infrastructure\Models\Supplier;
use App\Modules\Procurement\Infrastructure\Models\SupplierInvoice;
use Carbon\CarbonImmutable;

it('declares its key and a known ability', function () {
    $section = app(ProcurementSection::class);

    expect($section->key())->toBe('procurement')
        ->and($section->ability())->toBe('manage-procurement')
        ->and(AccessCatalogue::keys())->toContain($section->ability());
});

it('summarises purchase orders, debt and purchases', function () {
    $supplier = Supplier::factory()->create(['name' => 'Alpha Distribution']);

    PurchaseOrder::create(['supplier_id' => $supplier->id, 'status' => PurchaseOrderStatus::Ordered, 'expected_at' => now()->subDays(2)->toDateString()]);
    PurchaseOrder::create(['supplier_id' => $supplier->id, 'status' => PurchaseOrderStatus::PartiallyReceived, 'expected_at' => now()->addDays(5)->toDateString()]);
    PurchaseOrder::create(['supplier_id' => $supplier->id, 'status' => PurchaseOrderStatus::Draft]);
    PurchaseOrder::create(['supplier_id' => $supplier->id, 'status' => PurchaseOrderStatus::Received, 'expected_at' => now()->subDays(9)->toDateString()]);

    SupplierInvoice::factory()->create(['supplier_id' => $supplier->id, 'number' => 'F-LATE', 'amount' => 100000, 'paid_amount' => 40000, 'due_date' => now()->addDays(2)->toDateString()]);
    SupplierInvoice::factory()->create(['supplier_id' => $supplier->id, 'number' => 'F-SOON', 'amount' => 50000, 'paid_amount' => 0, 'due_date' => now()->addDays(1)->toDateString()]);
    SupplierInvoice::factory()->create(['supplier_id' => $supplier->id, 'number' => 'F-PAID', 'amount' => 70000, 'paid_amount' => 70000]);

    $data = app(ProcurementSection::class)->build(CarbonImmutable::now()->subDays(7), CarbonImmutable::now());

    expect($data['link'])->toBe('/achats');

    $kpis = collect($data['kpis'])->keyBy('key');
    expect($kpis['po_open']['value'])->toBe(2)
        ->and($kpis['po_late']['value'])->toBe(1)
        ->and($kpis['po_late']['tone'])->toBe('bad')
        ->and($kpis['invoices_unpaid']['value'])->toBe(2)
        ->and($kpis['supplier_debt']['value'])->toBe(110000)
        ->and($kpis['supplier_debt']['format'])->toBe('money')
        ->and($kpis['supplier_debt']['tone'])->toBe('warn')
        ->and($kpis['purchases']['value'])->toBe(220000);

    expect($data['charts'][0]['type'])->toBe('bar')
        ->and($data['charts'][0]['points'])->toHaveCount(1)
        ->and($data['charts'][0]['points'][0]['label'])->toBe(now()->toDateString())
        ->and($data['charts'][0]['points'][0]['value'])->toBe(220000);

    $rows = $data['tables'][0]['rows'];
    expect($rows)->toHaveCount(2)
        ->and($rows[0][0])->toBe('Alpha Distribution')
        ->and($rows[0][1])->toBe('F-SOON')
        ->and($rows[0][3])->toBe(50000)
        ->and($rows[1][1])->toBe('F-LATE')
        ->and($rows[1][3])->toBe(60000);
});

it('is robust on an empty database', function () {
    $data = app(ProcurementSection::class)->build(CarbonImmutable::now()->subDays(7), CarbonImmutable::now());

    $kpis = collect($data['kpis'])->keyBy('key');
    expect($kpis['po_open']['value'])->toBe(0)
        ->and($kpis['supplier_debt']['value'])->toBe(0)
        ->and($kpis['supplier_debt']['tone'])->toBeNull()
        ->and($data['charts'][0]['points'])->toBe([])
        ->and($data['tables'][0]['rows'])->toBe([]);
});
