<?php

declare(strict_types=1);

namespace App\Modules\Qr\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QrPayloadRequest extends FormRequest
{
    public const KINDS = ['product', 'order', 'invoice', 'supply'];

    public function authorize(): bool
    {
        return true; // gated by can:generate-qrcodes on the route, then by the resource's own ability
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(self::KINDS)],
            // A SKU, an order number, an invoice number or a supply number: letters, digits and - _ . /
            'reference' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._\\/-]*$/'],
        ];
    }
}
