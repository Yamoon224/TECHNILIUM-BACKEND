<?php

declare(strict_types=1);

namespace App\Modules\Returns\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenReturnRequest extends FormRequest
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
            'order_number' => ['required', 'string', 'max:50'],
            'reason' => ['required', 'string', 'max:255'],
            // Omitted = the articles' value, or the whole refundable balance.
            'amount' => ['nullable', 'integer', 'min:1'],
            // Optional: which articles come back (checked against the order).
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.offer_id' => ['required_with:items', 'integer', 'min:1'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
        ];
    }
}
