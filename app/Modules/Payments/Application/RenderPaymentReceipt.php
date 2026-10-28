<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application;

use App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface;
use App\Modules\Payments\Domain\PaymentStatus;
use App\Modules\Payments\Domain\ReceiptLabels;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Shared\Support\BrandLogo;
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

    /** The A5 landscape sheet, or the narrow 80 mm roll of a thermal printer. */
    public const FORMATS = ['a5', 'ticket'];

    public static function normalizeFormat(?string $format): string
    {
        return $format === 'ticket' ? 'ticket' : 'a5';
    }

    public function html(Payment $payment, string $locale = 'fr', string $format = 'a5'): string
    {
        $labels = ReceiptLabels::for(ReceiptLabels::normalize($locale));
        $order = $this->orders->find($payment->order_number);
        $money = fn (int $amount) => number_format($amount, 0, ',', "\u{00A0}")."\u{00A0}".($payment->currency === 'XOF' ? "F\u{00A0}CFA" : $payment->currency);

        return view($format === 'ticket' ? 'payments::ticket' : 'payments::receipt', [
            'payment' => $payment,
            't' => $labels,
            'number' => $this->number($payment),
            'company' => (array) config('invoicing.company'),
            'logo' => BrandLogo::dataUri(),
            'lines' => array_map(fn ($line) => ['description' => $line->description, 'quantity' => $line->quantity, 'unit' => $money($line->unitPriceAmount), 'total' => $money($line->lineTotalAmount)], $order?->lines ?? []),
            'tendered' => $payment->tendered_amount !== null ? $money($payment->tendered_amount) : null,
            'change' => $payment->tendered_amount !== null ? $money(max(0, $payment->tendered_amount - $payment->amount_amount)) : null,
            'customerName' => $order?->customerName ?? '',
            'customerPhone' => $order?->customerPhone ?? '',
            'methodLabel' => $labels[$payment->method->value] ?? $payment->method->value,
            'provider' => $payment->provider !== null ? ucwords(str_replace('_', ' ', $payment->provider)) : null,
            'amount' => $money($payment->amount_amount),
            'refunded' => $payment->refunded_amount > 0 ? $money($payment->refunded_amount) : null,
            'net' => $payment->refunded_amount > 0 ? $money($payment->amount_amount - $payment->refunded_amount) : null,
        ])->render();
    }

    public function pdf(Payment $payment, string $locale = 'fr', string $format = 'a5'): string
    {
        $pdf = Pdf::loadHTML($this->html($payment, $locale, $format))->setOption('isRemoteEnabled', false);

        if ($format === 'ticket') {
            // 80 mm = 226.77 pt wide; the roll is cut after the last line, so the height follows the content.
            $lines = count($this->orders->find($payment->order_number)?->lines ?? []);

            return $pdf->setPaper([0, 0, 226.77, 360 + 28 * $lines])->output();
        }

        return $pdf->setPaper('a5', 'landscape')->output();
    }

    public function filename(Payment $payment, string $format = 'a5'): string
    {
        return $this->number($payment).($format === 'ticket' ? '-ticket' : '').'.pdf';
    }
}
