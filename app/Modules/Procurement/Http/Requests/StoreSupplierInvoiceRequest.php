<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')],
            // When given, the purchase order must be this supplier's.
            'purchase_order_id' => [
                'nullable', 'integer',
                Rule::exists('purchase_orders', 'id')->where('supplier_id', $this->input('supplier_id')),
            ],
            // The supplier's own invoice number: unique per supplier, so a bill is never entered twice.
            'number' => [
                'required', 'string', 'max:60',
                Rule::unique('supplier_invoices', 'number')->where('supplier_id', $this->input('supplier_id')),
            ],
            'kind' => ['required', Rule::in(['goods', 'landed_cost'])],
            'amount' => ['required', 'integer', 'min:1'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
        ];
    }
}
