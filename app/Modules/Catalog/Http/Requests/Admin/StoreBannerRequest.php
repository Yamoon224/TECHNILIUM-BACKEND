<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Modules\Catalog\Infrastructure\Models\Banner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBannerRequest extends FormRequest
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
            'placement' => ['required', Rule::in(Banner::PLACEMENTS)],
            // mimes checks the real content type; no SVG (script carrier).
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=600,min_height=100,max_width=6000,max_height=6000'],
            'title' => ['nullable', 'string', 'max:160'],
            // http(s) or a path of the store: never javascript: or data:.
            'link_url' => ['nullable', 'string', 'max:500', 'regex:#^(https?://|/)#i'],
        ];
    }
}
