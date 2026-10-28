<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelInternalSaleRequest extends FormRequest
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
            // A refund of the till's money always needs a stated reason.
            'reason' => ['required', 'string', 'min:3', 'max:400'],
        ];
    }
}
