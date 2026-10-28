<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Gateways;

use App\Modules\Payments\Application\Contracts\PaymentLookupInterface;
use App\Modules\Payments\Application\Data\CollectedPaymentData;
use App\Modules\Payments\Application\RenderPaymentReceipt;
use App\Modules\Payments\Domain\PaymentStatus;
use App\Modules\Payments\Infrastructure\Models\Payment;

final class EloquentPaymentLookup implements PaymentLookupInterface
{
    public function __construct(private readonly RenderPaymentReceipt $receipts) {}

    public function receiptPdfForOrder(string $orderNumber, string $locale = 'fr', string $format = 'a5'): ?array
    {
        $payment = Payment::query()
            ->where('order_number', $orderNumber)
            ->whereIn('status', [PaymentStatus::Completed, PaymentStatus::Refunded])
            ->orderBy('id')
            ->first();

        return $payment === null ? null : [
            'filename' => $this->receipts->filename($payment, $format),
            'content' => $this->receipts->pdf($payment, $locale, $format),
        ];
    }

    public function collectedForOrders(array $orderNumbers): array
    {
        if ($orderNumbers === []) {
            return [];
        }

        $found = [];

        Payment::query()
            ->whereIn('order_number', $orderNumbers)
            ->whereIn('status', [PaymentStatus::Completed, PaymentStatus::Refunded])
            ->orderBy('id')
            ->get()
            ->each(function (Payment $payment) use (&$found) {
                $found[$payment->order_number] ??= new CollectedPaymentData(
                    $payment->id,
                    $this->receipts->number($payment),
                    $payment->order_number,
                    $payment->method->value,
                    $payment->provider,
                    $payment->amount_amount,
                    $payment->tendered_amount,
                    $payment->currency,
                    $payment->paid_at?->toIso8601String(),
                );
            });

        return $found;
    }
}
