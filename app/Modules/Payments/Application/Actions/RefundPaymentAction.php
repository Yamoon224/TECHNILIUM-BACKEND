<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Payments\Domain\Exceptions\InvalidRefundException;
use App\Modules\Payments\Domain\PaymentStatus;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Payments\Infrastructure\Models\PaymentRefund;
use App\Modules\Shared\Events\PaymentSettled;
use Illuminate\Support\Facades\DB;

/**
 * Refunds all or part of a collected payment. The payment stays "completed"
 * while part of it is refundable and becomes "refunded" once fully given
 * back; every refund is recorded and booked to the ledger on its own.
 */
final class RefundPaymentAction
{
    public function execute(Payment $payment, ?int $amount = null, ?string $reason = null, ?int $createdBy = null): Payment
    {
        return DB::transaction(function () use ($payment, $amount, $reason, $createdBy) {
            // Lock so two concurrent refunds cannot both pass the cap below.
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== PaymentStatus::Completed || $payment->refundable() === 0) {
                throw InvalidRefundException::notRefundable($payment->id);
            }

            $amount ??= $payment->refundable();

            if ($amount < 1 || $amount > $payment->refundable()) {
                throw InvalidRefundException::amountExceedsRefundable($payment->refundable());
            }

            $refund = PaymentRefund::query()->create([
                'payment_id' => $payment->id,
                'amount' => $amount,
                'reason' => $reason,
                'created_by' => $createdBy,
            ]);

            $payment->refunded_amount += $amount;

            if ($payment->refundable() === 0) {
                $payment->status = PaymentStatus::Refunded;
            }

            $payment->save();

            event(new PaymentSettled(
                $payment->id,
                $payment->order_number,
                $payment->method->value,
                $amount,
                $payment->currency,
                true,
                $refund->id,
            ));

            return $payment;
        });
    }
}
