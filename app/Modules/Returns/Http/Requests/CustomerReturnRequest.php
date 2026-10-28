<?php

declare(strict_types=1);

namespace App\Modules\Returns\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A customer asks to send articles back: which ones and why — never an amount (it is computed). */
class CustomerReturnRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:5', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.offer_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }
}
