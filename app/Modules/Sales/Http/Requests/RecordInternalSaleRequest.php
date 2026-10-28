<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordInternalSaleRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.offer_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            // A walk-in customer may stay anonymous: every field is optional.
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email:rfc', 'max:255'],
            'locale' => ['nullable', 'string', 'in:fr,en'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'method' => ['required', Rule::in(['cash', 'mobile_money'])],
            'provider' => ['required_if:method,mobile_money', 'nullable', Rule::in(['orange_money', 'mtn_money', 'wave'])],
            'provider_reference' => ['nullable', 'string', 'max:100'],
            'tendered_amount' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
