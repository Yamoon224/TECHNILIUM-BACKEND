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
            // Vendors list against an existing, published product variant -
            // only staff create new products (cahier §6.1), and a vendor
            // must not be able to attach a listing to a draft/archived one
            // just by guessing its id. Vendors can't touch Catalog's
            // Eloquent models, so this checks by table/column name.
            'variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')->where(
                fn ($query) => $query->whereIn('product_id', fn ($sub) => $sub->select('id')->from('products')->where('status', 'published')),
            )],
            'price_amount' => ['required', 'integer', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'warranty_months' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
