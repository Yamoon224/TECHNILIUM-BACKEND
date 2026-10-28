<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseTillRequest extends FormRequest
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
            'counted_cash' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
