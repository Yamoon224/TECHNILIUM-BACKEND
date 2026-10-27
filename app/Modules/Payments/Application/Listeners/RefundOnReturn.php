<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Listeners;

use App\Modules\Payments\Application\Actions\RefundPaymentAction;
use App\Modules\Payments\Domain\PaymentStatus;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Shared\Events\ReturnRefunded;

/**
 * A refunded return gives the money back on the payment(s) that collected
 * it, up to what each can still refund. An order not yet paid (cash on
 * delivery not collected) has nothing to give back.
 */
final class RefundOnReturn
{
    public function __construct(private readonly RefundPaymentAction $refund) {}

    public function handle(ReturnRefunded $event): void
    {
        $remaining = $event->amount;

        $payments = Payment::query()
            ->where('order_number', $event->orderNumber)
            ->where('status', PaymentStatus::Completed)
            ->orderBy('id')
            ->get();

        foreach ($payments as $payment) {
            if ($remaining <= 0) {
                break;
            }

            $part = min($remaining, $payment->refundable());

            if ($part > 0) {
                $this->refund->execute($payment, $part, 'Retour RMA-'.sprintf('%06d', $event->returnId).' : '.$event->reason);
                $remaining -= $part;
            }
        }
    }
}
