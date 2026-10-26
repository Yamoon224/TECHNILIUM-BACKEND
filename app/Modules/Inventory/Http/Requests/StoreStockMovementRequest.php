<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockMovementRequest extends FormRequest
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
            // Inventory can't touch Catalog's Eloquent models, so this
            // checks by table name (cahier §9.1).
            'offer_id' => ['required', 'integer', Rule::exists('offers', 'id')],
            'quantity_delta' => ['required', 'integer', 'not_in:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
