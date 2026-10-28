<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by can:manage-catalog on the route group
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // mimes checks the real content type, not just the extension; no SVG (script carrier).
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=200,min_height=200,max_width=6000,max_height=6000'],
        ];
    }
}
