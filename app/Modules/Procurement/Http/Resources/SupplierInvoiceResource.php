<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Http\Resources;

use App\Modules\Procurement\Infrastructure\Models\SupplierInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierInvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SupplierInvoice $invoice */
        $invoice = $this->resource;

        return [
            'id' => $invoice->id,
            'supplier_id' => $invoice->supplier_id,
            'supplier_name' => $invoice->relationLoaded('supplier') ? $invoice->supplier->name : null,
            'purchase_order_id' => $invoice->purchase_order_id,
            'number' => $invoice->number,
            'kind' => $invoice->kind,
            'amount' => $invoice->amount,
            'paid_amount' => $invoice->paid_amount,
            'outstanding' => $invoice->outstanding(),
            'settled' => $invoice->isSettled(),
            'overdue' => $invoice->isOverdue(),
            'invoice_date' => $invoice->invoice_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
        ];
    }
}
