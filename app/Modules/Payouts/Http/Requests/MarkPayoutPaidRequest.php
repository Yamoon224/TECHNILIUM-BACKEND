<?php

declare(strict_types=1);

namespace App\Modules\Payouts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkPayoutPaidRequest extends FormRequest
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
            'payment_reference' => ['required', 'string', 'max:100'],
        ];
    }
}
