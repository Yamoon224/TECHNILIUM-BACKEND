<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Reply body for both sides; only the team may add `internal`. */
class ReplyTicketRequest extends FormRequest
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
            'body' => ['required', 'string', 'min:1', 'max:5000'],
            'internal' => ['nullable', 'boolean'],
        ];
    }
}
