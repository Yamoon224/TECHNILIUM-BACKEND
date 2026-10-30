<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Payments\Domain\Exceptions\InvalidPaymentTransitionException;
use App\Modules\Payments\Domain\PaymentStatus;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Shared\Events\PaymentSettled;
use Illuminate\Support\Facades\DB;

/**
 * Single entry point for every payment status change - mirrors Catalog's
 * TransitionOfferStatusAction so a status is never written outside of an
 * authorized transition.
 */
final class TransitionPaymentStatusAction
{
    public function execute(Payment $payment, PaymentStatus $target): Payment
    {
        $current = $payment->status;

        if (! $current->canTransitionTo($target)) {
            throw InvalidPaymentTransitionException::from($current, $target);
        }

        // Status change and its ledger entry commit together or not at all.
        DB::transaction(function () use ($payment, $target) {
            $payment->status = $target;
            $payment->paid_at = $target === PaymentStatus::Completed ? now() : $payment->paid_at;
            $payment->save();

            // Refunds go through RefundPaymentAction (partial refunds, one ledger entry each).
            if ($target === PaymentStatus::Completed) {
                event(new PaymentSettled(
                    $payment->id,
                    $payment->order_number,
                    $payment->method->value,
                    $payment->amount_amount,
                    $payment->currency,
                    false,
                ));
            }
        });

        return $payment->refresh();
    }
}
