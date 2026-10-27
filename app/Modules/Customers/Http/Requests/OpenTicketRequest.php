<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Requests;

use App\Modules\Customers\Domain\TicketCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenTicketRequest extends FormRequest
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
            'subject' => ['required', 'string', 'min:3', 'max:150'],
            'category' => ['required', Rule::enum(TicketCategory::class)],
            'order_number' => ['nullable', 'string', 'max:50'],
            'message' => ['required', 'string', 'min:3', 'max:5000'],
        ];
    }
}
