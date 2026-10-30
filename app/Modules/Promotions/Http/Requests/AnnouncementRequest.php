<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Creation (image required) and update (image optional: it replaces the current one). */
class AnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by can:manage-promotions on the route group
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST') && $this->route('announcement') === null;

        return [
            'title' => ['required', 'string', 'max:160'],
            // mimes checks the real content type; no SVG (script carrier).
            'image' => [$creating ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=100,min_height=100,max_width=6000,max_height=6000'],
            // http(s) or a path of the store: never javascript: or data:.
            'link_url' => ['nullable', 'string', 'max:500', 'regex:#^(https?://|/)#i'],
            'width' => ['required', 'integer', 'min:100', 'max:2000'],
            'height' => ['required', 'integer', 'min:100', 'max:2000'],
            'delay_seconds' => ['required', 'integer', 'min:0', 'max:600'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
