<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuditFilterRequest extends FormRequest
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
            'user_id' => ['nullable', 'integer', 'min:1'],
            'method' => ['nullable', 'in:POST,PUT,PATCH,DELETE'],
            'path' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'integer', 'between:100,599'],
            'failed' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
