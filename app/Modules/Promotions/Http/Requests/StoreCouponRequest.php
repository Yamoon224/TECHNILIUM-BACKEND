<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Http\Requests;

use App\Modules\Promotions\Domain\CouponType;
use App\Modules\Promotions\Infrastructure\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Codes are case-insensitive: normalise before the uniqueness check.
        if ($this->filled('code')) {
            $this->merge(['code' => Coupon::normalize($this->string('code')->toString())]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons', 'code')],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => [
                'required', 'integer', 'min:1',
                // A percentage is a whole number of 1-100.
                Rule::when($this->input('type') === CouponType::Percent->value, ['max:100']),
            ],
            'min_order_amount' => ['nullable', 'integer', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_customer' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
    }
}
