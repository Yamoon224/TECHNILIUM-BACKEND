<?php

declare(strict_types=1);

namespace App\Modules\Vendors\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVendorOfferRequest extends FormRequest
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
            // Vendors list against an existing product variant — only staff
            // create new products (cahier §6.1); Vendors can't touch
            // Catalog's Eloquent models, so this checks by table name.
            'variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')],
            'price_amount' => ['required', 'integer', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'warranty_months' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
