<?php

declare(strict_types=1);

namespace App\Modules\Payouts\Application\Gateways;

use App\Modules\Payouts\Application\Contracts\VendorPayoutReaderInterface;
use App\Modules\Payouts\Application\Data\VendorPayoutData;
use App\Modules\Payouts\Infrastructure\Models\Payout;

final class EloquentVendorPayoutReader implements VendorPayoutReaderInterface
{
    public function listFor(int $vendorId): array
    {
        return Payout::query()
            ->where('vendor_id', $vendorId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (Payout $payout) => $this->toData($payout, false))
            ->all();
    }

    public function findFor(int $vendorId, int $payoutId): ?VendorPayoutData
    {
        $payout = Payout::query()->with('items')->where('vendor_id', $vendorId)->find($payoutId);

        return $payout ? $this->toData($payout, true) : null;
    }

    private function toData(Payout $payout, bool $withItems): VendorPayoutData
    {
        return new VendorPayoutData(
            id: $payout->id,
            status: $payout->status->value,
            currency: $payout->currency,
            commissionRatePercent: $payout->commission_basis_points / 100,
            grossAmount: $payout->gross_amount,
            commissionAmount: $payout->commission_amount,
            netAmount: $payout->net_amount,
            paymentReference: $payout->payment_reference,
            paidAt: $payout->paid_at?->toIso8601String(),
            createdAt: $payout->created_at?->toIso8601String(),
            items: $withItems
                ? $payout->items->map(fn ($item) => [
                    'order_number' => $item->order_number,
                    'gross_amount' => $item->gross_amount,
                    'commission_amount' => $item->commission_amount,
                    'refund_deduction_amount' => $item->refund_deduction_amount,
                ])->all()
                : null,
        );
    }
}
