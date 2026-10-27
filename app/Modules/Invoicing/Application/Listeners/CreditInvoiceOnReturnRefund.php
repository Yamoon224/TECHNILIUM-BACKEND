<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Application\Listeners;

use App\Modules\Invoicing\Application\Actions\IssueCreditNoteAction;
use App\Modules\Invoicing\Domain\InvoiceType;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Shared\Events\ReturnRefunded;

/**
 * A refunded return reduces what was invoiced: issue a credit note on the
 * order's live invoice. An order that was never invoiced has nothing to credit.
 */
final class CreditInvoiceOnReturnRefund
{
    public function __construct(private readonly IssueCreditNoteAction $creditNotes) {}

    public function handle(ReturnRefunded $event): void
    {
        $invoice = Invoice::query()
            ->where('order_number', $event->orderNumber)
            ->where('type', InvoiceType::Invoice->value)
            ->get()
            ->first(fn (Invoice $candidate) => $candidate->remainingCreditable() > 0);

        if ($invoice === null) {
            return;
        }

        $this->creditNotes->execute(
            $invoice,
            'Retour RMA-'.sprintf('%06d', $event->returnId).' : '.$event->reason,
            null,
            min($event->amount, $invoice->remainingCreditable()),
        );
    }
}
