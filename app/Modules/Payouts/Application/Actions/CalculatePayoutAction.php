<?php

declare(strict_types=1);

namespace App\Modules\Payouts\Application\Actions;

use App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface;
use App\Modules\Payouts\Domain\CommissionCalculator;
use App\Modules\Payouts\Domain\Exceptions\PayoutException;
use App\Modules\Payouts\Domain\PayoutStatus;
use App\Modules\Payouts\Infrastructure\Models\Payout;
use App\Modules\Payouts\Infrastructure\Models\PayoutItem;
use App\Modules\Returns\Application\Contracts\RefundLookupInterface;
use App\Modules\Shared\Events\PayoutCalculated;
use Illuminate\Support\Facades\DB;

/**
 * Builds a vendor's payout from each of its delivered shares (sub-orders)
 * past the return window that has not been paid yet: gross = the vendor's
 * lines, minus refunds, minus commission (cahier §6.8). Shares already in a
 * payout are skipped, so running it twice never pays twice - and one
 * vendor's delivery never waits for another vendor's.
 */
final class CalculatePayoutAction
{
    public function __construct(
        private readonly OrderLookupGatewayInterface $orders,
        private readonly RefundLookupInterface $refunds,
    ) {}

    public function execute(int $vendorId, ?int $createdBy = null): Payout
    {
        $cutoff = now()->subDays((int) config('payouts.return_delay_days'));
        $calculator = CommissionCalculator::fromPercent((float) config('payouts.commission_percent'));

        return DB::transaction(function () use ($vendorId, $createdBy, $cutoff, $calculator) {
            $alreadyPaid = PayoutItem::query()->where('vendor_id', $vendorId)->pluck('order_number')->all();

            $items = [];
            $currency = 'XOF';

            foreach ($this->orders->deliveredVendorSharesUntil($vendorId, $cutoff) as $share) {
                // A return still being decided keeps the money in suspense.
                if (in_array($share->orderNumber, $alreadyPaid, true) || $this->refunds->hasOpenReturn($share->orderNumber)) {
                    continue;
                }

                $gross = $share->grossAmount;

                // Refunds are borne by the vendor in proportion to its share of
                // the order, so the commission is only taken on what stayed sold.
                $deduction = 0;
                $refunded = $this->refunds->refundedAmount($share->orderNumber);

                if ($refunded > 0 && $share->orderSubtotalAmount > 0) {
                    $deduction = min($gross, intdiv($refunded * $gross + intdiv($share->orderSubtotalAmount, 2), $share->orderSubtotalAmount));
                    $gross -= $deduction;
                }

                if ($gross > 0) {
                    $currency = $share->currency;
                    $items[] = [
                        'order_number' => $share->orderNumber,
                        'gross_amount' => $gross,
                        'commission_amount' => $calculator->commissionOn($gross),
                        'refund_deduction_amount' => $deduction,
                    ];
                }
            }

            if ($items === []) {
                throw PayoutException::nothingPayable($vendorId);
            }

            $gross = array_sum(array_column($items, 'gross_amount'));
            $commission = array_sum(array_column($items, 'commission_amount'));

            $payout = Payout::query()->create([
                'vendor_id' => $vendorId,
                // Vendors owns its table; read by name, as Payments does for orders.
                'vendor_name' => (string) DB::table('vendors')->where('id', $vendorId)->value('company_name'),
                'status' => PayoutStatus::Pending,
                'currency' => $currency,
                'commission_basis_points' => $calculator->basisPoints,
                'gross_amount' => $gross,
                'commission_amount' => $commission,
                'net_amount' => $gross - $commission,
                'created_by' => $createdBy,
            ]);

            // The unique (vendor_id, order_number) index rejects a concurrent
            // run that picked the same shares; the loser's transaction rolls back.
            foreach ($items as $item) {
                $payout->items()->create($item + ['vendor_id' => $vendorId]);
            }

            event(new PayoutCalculated(
                $payout->id, $vendorId, $gross, $commission, $gross - $commission, $currency,
            ));

            return $payout->load('items');
        });
    }
}
