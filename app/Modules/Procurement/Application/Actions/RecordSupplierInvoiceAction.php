<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Application\Actions;

use App\Modules\Procurement\Infrastructure\Models\PurchaseOrder;
use App\Modules\Procurement\Infrastructure\Models\Supplier;
use App\Modules\Procurement\Infrastructure\Models\SupplierInvoice;
use Illuminate\Support\Carbon;

/**
 * Registers an invoice received from a supplier (cahier §6.4). The due date
 * defaults to the invoice date plus the supplier's payment terms.
 */
final class RecordSupplierInvoiceAction
{
    public function execute(
        Supplier $supplier,
        string $number,
        string $kind,
        int $amount,
        string $invoiceDate,
        ?string $dueDate,
        ?PurchaseOrder $order,
    ): SupplierInvoice {
        $date = Carbon::parse($invoiceDate);

        return SupplierInvoice::query()->create([
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $order?->id,
            'number' => $number,
            'kind' => $kind,
            'amount' => $amount,
            'paid_amount' => 0,
            'invoice_date' => $date->toDateString(),
            'due_date' => $dueDate ?? $date->copy()->addDays($supplier->payment_terms_days)->toDateString(),
        ]);
    }
}
