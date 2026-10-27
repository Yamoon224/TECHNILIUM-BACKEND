<?php

declare(strict_types=1);

namespace App\Modules\Payouts\Http\Resources;

use App\Modules\Payouts\Infrastructure\Models\Payout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Payout $payout */
        $payout = $this->resource;

        return [
            'id' => $payout->id,
            'vendor_id' => $payout->vendor_id,
            'vendor_name' => $payout->vendor_name,
            'status' => $payout->status->value,
            'currency' => $payout->currency,
            'commission_rate_percent' => $payout->commission_basis_points / 100,
            'gross_amount' => $payout->gross_amount,
            'commission_amount' => $payout->commission_amount,
            'net_amount' => $payout->net_amount,
            'payment_reference' => $payout->payment_reference,
            'paid_at' => $payout->paid_at?->toIso8601String(),
            'created_at' => $payout->created_at?->toIso8601String(),
            'items' => $payout->relationLoaded('items')
                ? $payout->items->map(fn ($item) => [
                    'order_number' => $item->order_number,
                    'gross_amount' => $item->gross_amount,
                    'commission_amount' => $item->commission_amount,
                    'refund_deduction_amount' => $item->refund_deduction_amount,
                ])->all()
                : null,
        ];
    }
}
