<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Application\Actions;

use App\Modules\Procurement\Domain\Exceptions\ProcurementException;
use App\Modules\Procurement\Infrastructure\Models\SupplierInvoice;
use App\Modules\Procurement\Infrastructure\Models\SupplierPayment;
use App\Modules\Shared\Events\SupplierPaid;
use Illuminate\Support\Facades\DB;

/** Settles all or part of a supplier invoice; never more than what is still owed. */
final class PaySupplierInvoiceAction
{
    public function execute(SupplierInvoice $invoice, ?int $amount, string $method, ?string $reference, ?int $paidBy): SupplierPayment
    {
        return DB::transaction(function () use ($invoice, $amount, $method, $reference, $paidBy) {
            // Lock so a double click or two accountants cannot overpay the same invoice.
            $invoice = SupplierInvoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->isSettled()) {
                throw ProcurementException::invoiceSettled($invoice->number);
            }

            $amount ??= $invoice->outstanding();

            if ($amount < 1 || $amount > $invoice->outstanding()) {
                throw ProcurementException::amountExceedsOutstanding($invoice->outstanding());
            }

            $payment = SupplierPayment::query()->create([
                'supplier_invoice_id' => $invoice->id,
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'paid_by' => $paidBy,
            ]);

            $invoice->paid_amount += $amount;
            $invoice->save();

            event(new SupplierPaid($payment->id, $invoice->number, $invoice->kind, $amount, 'XOF'));

            return $payment;
        });
    }
}
