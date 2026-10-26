<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShipmentRequest extends FormRequest
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
            // Shipping can't touch Orders'/Identity's Eloquent models, so
            // these check by table/column name (cahier §9.1).
            'order_number' => ['required', 'string', Rule::exists('orders', 'order_number'), Rule::unique('shipments', 'order_number')],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }
}
