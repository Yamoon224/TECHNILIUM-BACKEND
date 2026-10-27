<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Application\Actions;

use App\Modules\Invoicing\Application\InvoiceNumberGenerator;
use App\Modules\Invoicing\Domain\Exceptions\InvoiceException;
use App\Modules\Invoicing\Domain\InvoiceType;
use App\Modules\Invoicing\Domain\VatBreakdown;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface;
use App\Modules\Shared\ValueObjects\Currency;
use App\Modules\Shared\ValueObjects\Money;
use App\Modules\Shared\ValueObjects\TauxTVA;
use Illuminate\Support\Facades\DB;

final class IssueInvoiceAction
{
    /** Order statuses from which a sale is invoiceable (not pending, not cancelled). */
    private const INVOICEABLE_STATUSES = ['confirmed', 'shipped', 'delivered'];

    public function __construct(
        private readonly OrderLookupGatewayInterface $orders,
        private readonly InvoiceNumberGenerator $numbers,
    ) {}

    public function execute(string $orderNumber, ?int $issuedBy = null): Invoice
    {
        $order = $this->orders->find($orderNumber)
            ?? throw InvoiceException::orderNotFound($orderNumber);

        if (! in_array($order->status, self::INVOICEABLE_STATUSES, true)) {
            throw InvoiceException::orderNotInvoiceable($orderNumber, $order->status);
        }

        return DB::transaction(function () use ($order, $orderNumber, $issuedBy) {
            // An order has one live invoice; after it is cancelled by a
            // credit note, a corrected one may be issued (cahier §6.7).
            $hasLiveInvoice = Invoice::query()
                ->where('order_number', $orderNumber)
                ->where('type', InvoiceType::Invoice->value)
                ->whereNotIn('id', Invoice::query()->whereNotNull('credited_invoice_id')->select('credited_invoice_id'))
                ->lockForUpdate()
                ->exists();

            if ($hasLiveInvoice) {
                throw InvoiceException::alreadyInvoiced($orderNumber);
            }

            $rate = TauxTVA::ofPercent((float) config('invoicing.vat_percent'));
            $vat = VatBreakdown::fromGross(
                Money::ofMinorUnits($order->subtotalAmount, Currency::from($order->currency)),
                $rate,
            );

            $now = now();
            $invoice = Invoice::query()->create([
                'number' => $this->numbers->next(InvoiceType::Invoice, $now->year),
                'type' => InvoiceType::Invoice,
                'order_number' => $orderNumber,
                'customer_name' => $order->customerName,
                'currency' => $order->currency,
                'vat_basis_points' => $rate->basisPoints,
                'net_amount' => $vat->net->amount,
                'vat_amount' => $vat->vat->amount,
                'total_amount' => $vat->gross->amount,
                'issued_by' => $issuedBy,
                'issued_at' => $now,
            ]);

            foreach ($order->lines as $line) {
                $invoice->lines()->create([
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_price_amount' => $line->unitPriceAmount,
                    'line_total_amount' => $line->lineTotalAmount,
                ]);
            }

            return $invoice->load('lines');
        });
    }
}
