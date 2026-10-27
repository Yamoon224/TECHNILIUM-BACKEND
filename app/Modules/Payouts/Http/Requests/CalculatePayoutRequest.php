<?php

declare(strict_types=1);

namespace App\Modules\Payouts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalculatePayoutRequest extends FormRequest
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
            // By table name: Payouts never touches Vendors' models (cahier §9.1).
            'vendor_id' => ['required', 'integer', Rule::exists('vendors', 'id')],
        ];
    }
}
