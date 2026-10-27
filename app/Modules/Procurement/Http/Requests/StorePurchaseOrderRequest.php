<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
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
            // By table name: Procurement owns suppliers, so this is its own data.
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('active', true)],
            'expected_at' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.offer_id' => ['required', 'integer', 'min:1'],
            'lines.*.quantity' => ['required', 'integer', 'between:1,100000'],
            'lines.*.unit_cost_amount' => ['required', 'integer', 'min:0'],
        ];
    }
}
