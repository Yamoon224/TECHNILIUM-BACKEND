<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Http\Resources;

use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Invoice $invoice */
        $invoice = $this->resource;

        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'type' => $invoice->type->value,
            'order_number' => $invoice->order_number,
            'credited_invoice_id' => $invoice->credited_invoice_id,
            'customer_name' => $invoice->customer_name,
            'currency' => $invoice->currency,
            'vat_rate_percent' => $invoice->vat_basis_points / 100,
            'net_amount' => $invoice->net_amount,
            'vat_amount' => $invoice->vat_amount,
            'total_amount' => $invoice->total_amount,
            'discount_amount' => $invoice->discount_amount,
            'coupon_code' => $invoice->coupon_code,
            'reason' => $invoice->reason,
            'issued_at' => $invoice->issued_at->toIso8601String(),
            'lines' => $invoice->relationLoaded('lines')
                ? $invoice->lines->map(fn ($line) => [
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_price_amount' => $line->unit_price_amount,
                    'line_total_amount' => $line->line_total_amount,
                ])->all()
                : null,
        ];
    }
}
