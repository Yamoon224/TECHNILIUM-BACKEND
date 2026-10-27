<?php

declare(strict_types=1);

namespace App\Modules\Payouts\Application\Actions;

use App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface;
use App\Modules\Payouts\Domain\CommissionCalculator;
use App\Modules\Returns\Application\Contracts\RefundLookupInterface;
use App\Modules\Payouts\Domain\Exceptions\PayoutException;
use App\Modules\Payouts\Domain\PayoutStatus;
use App\Modules\Payouts\Infrastructure\Models\Payout;
use App\Modules\Payouts\Infrastructure\Models\PayoutItem;
use App\Modules\Shared\Events\PayoutCalculated;
use Illuminate\Support\Facades\DB;

/**
 * Builds a vendor's payout from every delivered order past the return
 * window that has not been paid yet: gross = the vendor's lines, minus
 * commission (cahier §6.8). Orders already in a payout are skipped, so
 * running it twice never pays twice.
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

            foreach ($this->orders->deliveredUntil($cutoff) as $order) {
                // A return still being decided keeps the money in suspense.
                if (in_array($order->orderNumber, $alreadyPaid, true) || $this->refunds->hasOpenReturn($order->orderNumber)) {
                    continue;
                }

                $gross = 0;
                foreach ($order->lines as $line) {
                    if ($line->vendorId === $vendorId) {
                        $gross += $line->lineTotalAmount;
                    }
                }

                // Refunds are borne by the vendor in proportion to its share of
                // the order, so the commission is only taken on what stayed sold.
                $deduction = 0;
                $refunded = $this->refunds->refundedAmount($order->orderNumber);

                if ($refunded > 0 && $order->subtotalAmount > 0) {
                    $deduction = min($gross, intdiv($refunded * $gross + intdiv($order->subtotalAmount, 2), $order->subtotalAmount));
                    $gross -= $deduction;
                }

                if ($gross > 0) {
                    $currency = $order->currency;
                    $items[] = [
                        'order_number' => $order->orderNumber,
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
            // run that picked the same orders; the loser's transaction rolls back.
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
