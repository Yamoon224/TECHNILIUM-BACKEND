<?php

declare(strict_types=1);

namespace App\Modules\Returns\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Body shared by accept / reject / receive / refund; each endpoint uses what it needs. */
class DecideReturnRequest extends FormRequest
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
            'note' => ['nullable', 'string', 'max:1000'],
            'amount' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
