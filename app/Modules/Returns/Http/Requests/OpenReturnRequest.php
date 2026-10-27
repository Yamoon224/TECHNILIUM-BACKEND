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
            // Omitted = the whole refundable balance of the order.
            'amount' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
