<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveGoodsRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.line_id' => ['required', 'integer', 'min:1', 'distinct'],
            'lines.*.accepted' => ['required', 'integer', 'min:0', 'max:100000'],
            'lines.*.rejected' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
