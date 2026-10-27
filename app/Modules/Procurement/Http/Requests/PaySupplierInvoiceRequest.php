<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaySupplierInvoiceRequest extends FormRequest
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
            // Omitted = pay what is still owed.
            'amount' => ['nullable', 'integer', 'min:1'],
            'method' => ['required', Rule::in(['bank_transfer', 'mobile_money', 'cash', 'cheque'])],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
