<?php

declare(strict_types=1);

namespace App\Modules\Vendors\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVendorOfferRequest extends FormRequest
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
            'price_amount' => ['sometimes', 'integer', 'min:0'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0'],
            'warranty_months' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
