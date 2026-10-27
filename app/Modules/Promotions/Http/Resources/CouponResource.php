<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Http\Resources;

use App\Modules\Promotions\Infrastructure\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Coupon $coupon */
        $coupon = $this->resource;

        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'type' => $coupon->type->value,
            'value' => $coupon->value,
            'min_order_amount' => $coupon->min_order_amount,
            'max_uses' => $coupon->max_uses,
            'max_uses_per_customer' => $coupon->max_uses_per_customer,
            'used_count' => $coupon->used_count,
            'starts_at' => $coupon->starts_at?->toIso8601String(),
            'ends_at' => $coupon->ends_at?->toIso8601String(),
            'active' => $coupon->active,
            'created_at' => $coupon->created_at?->toIso8601String(),
        ];
    }
}
