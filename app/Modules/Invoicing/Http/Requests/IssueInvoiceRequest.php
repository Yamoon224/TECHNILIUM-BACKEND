<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IssueInvoiceRequest extends FormRequest
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
        ];
    }
}
