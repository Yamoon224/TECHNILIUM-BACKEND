<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
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
            // Payments can't touch Orders' Eloquent models, so this checks
            // by table/column name (cahier §9.1).
            'order_number' => ['required', 'string', Rule::exists('orders', 'order_number')],
            'method' => ['required', Rule::in(['cod', 'cash', 'mobile_money'])],
            'amount_amount' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'provider' => ['required_if:method,mobile_money', 'nullable', Rule::in(['orange_money', 'mtn_money', 'wave'])],
            'provider_reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
