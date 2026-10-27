<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Application;

use App\Modules\Invoicing\Domain\AmountInWords;
use App\Modules\Invoicing\Domain\InvoiceType;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renders an issued invoice or credit note as a PDF (cahier §6.7). The
 * document is generated from the immutable record each time, never stored:
 * since an issued document cannot change, the output is always the same.
 */
class RenderInvoiceDocument
{
    /** The HTML the PDF is made from — kept separate so it can be inspected and tested. */
    public function html(Invoice $invoice): string
    {
        $invoice->loadMissing('lines');

        return view('invoicing::document', [
            'invoice' => $invoice,
            'title' => $invoice->type === InvoiceType::CreditNote ? 'AVOIR' : 'FACTURE',
            'company' => (array) config('invoicing.company'),
            'amountInWords' => ucfirst(AmountInWords::francsCfa($invoice->total_amount)),
            // A credit note names the invoice it cancels.
            'creditedNumber' => $invoice->credited_invoice_id !== null
                ? Invoice::query()->whereKey($invoice->credited_invoice_id)->value('number')
                : null,
        ])->render();
    }

    public function pdf(Invoice $invoice): string
    {
        return Pdf::loadHTML($this->html($invoice))
            ->setPaper('a4')
            // Untrusted text (customer names, reasons) must never pull in a remote file.
            ->setOption('isRemoteEnabled', false)
            ->output();
    }

    public function filename(Invoice $invoice): string
    {
        return $invoice->number.'.pdf';
    }
}
