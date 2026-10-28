<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application;

use App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface;
use App\Modules\Payments\Domain\PaymentStatus;
use App\Modules\Payments\Domain\ReceiptLabels;
use App\Modules\Payments\Infrastructure\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;

/** The proof a customer keeps that a payment was received (never for a pending or failed one). */
class RenderPaymentReceipt
{
    public function __construct(private readonly OrderLookupGatewayInterface $orders) {}

    /** A receipt exists only for money actually received (completed, or since refunded). */
    public function isIssuable(Payment $payment): bool
    {
        return in_array($payment->status, [PaymentStatus::Completed, PaymentStatus::Refunded], true);
    }

    public function number(Payment $payment): string
    {
        return 'REC-'.sprintf('%06d', $payment->id);
    }

    public function html(Payment $payment, string $locale = 'fr'): string
    {
        $labels = ReceiptLabels::for(ReceiptLabels::normalize($locale));
        $order = $this->orders->find($payment->order_number);
        $money = fn (int $amount) => number_format($amount, 0, ',', "\u{00A0}")."\u{00A0}".($payment->currency === 'XOF' ? "F\u{00A0}CFA" : $payment->currency);

        return view('payments::receipt', [
            'payment' => $payment,
            't' => $labels,
            'number' => $this->number($payment),
            'company' => (array) config('invoicing.company'),
            'customerName' => $order?->customerName ?? '',
            'customerPhone' => $order?->customerPhone ?? '',
            'methodLabel' => $labels[$payment->method->value] ?? $payment->method->value,
            'provider' => $payment->provider !== null ? ucwords(str_replace('_', ' ', $payment->provider)) : null,
            'amount' => $money($payment->amount_amount),
            'refunded' => $payment->refunded_amount > 0 ? $money($payment->refunded_amount) : null,
            'net' => $payment->refunded_amount > 0 ? $money($payment->amount_amount - $payment->refunded_amount) : null,
        ])->render();
    }

    public function pdf(Payment $payment, string $locale = 'fr'): string
    {
        return Pdf::loadHTML($this->html($payment, $locale))
            ->setPaper('a5', 'landscape')
            ->setOption('isRemoteEnabled', false)
            ->output();
    }

    public function filename(Payment $payment): string
    {
        return $this->number($payment).'.pdf';
    }
}
