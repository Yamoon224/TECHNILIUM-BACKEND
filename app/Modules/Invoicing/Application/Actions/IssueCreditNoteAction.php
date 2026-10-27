<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Application\Actions;

use App\Modules\Invoicing\Application\InvoiceNumberGenerator;
use App\Modules\Invoicing\Domain\Exceptions\InvoiceException;
use App\Modules\Invoicing\Domain\InvoiceType;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Shared\Events\InvoiceIssued;
use Illuminate\Support\Facades\DB;

/**
 * Cancels an invoice in full by issuing a credit note that mirrors it
 * (cahier §6.7: a validated invoice is never edited). Partial credit notes
 * arrive with the Returns module.
 */
final class IssueCreditNoteAction
{
    public function __construct(private readonly InvoiceNumberGenerator $numbers) {}

    public function execute(Invoice $invoice, string $reason, ?int $issuedBy = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $reason, $issuedBy) {
            // Serialise concurrent credits of the same invoice; the unique
            // credited_invoice_id column is the final safety net.
            $alreadyCredited = Invoice::query()
                ->where('credited_invoice_id', $invoice->id)
                ->lockForUpdate()
                ->exists();

            if ($invoice->type !== InvoiceType::Invoice || $alreadyCredited) {
                throw InvoiceException::notCreditable($invoice->number);
            }

            $now = now();
            $creditNote = Invoice::query()->create([
                'number' => $this->numbers->next(InvoiceType::CreditNote, $now->year),
                'type' => InvoiceType::CreditNote,
                'order_number' => $invoice->order_number,
                'credited_invoice_id' => $invoice->id,
                'customer_name' => $invoice->customer_name,
                'currency' => $invoice->currency,
                'vat_basis_points' => $invoice->vat_basis_points,
                'net_amount' => $invoice->net_amount,
                'vat_amount' => $invoice->vat_amount,
                'total_amount' => $invoice->total_amount,
                'reason' => $reason,
                'issued_by' => $issuedBy,
                'issued_at' => $now,
            ]);

            foreach ($invoice->lines as $line) {
                $creditNote->lines()->create($line->only([
                    'description', 'quantity', 'unit_price_amount', 'line_total_amount',
                ]));
            }

            event(new InvoiceIssued(
                $creditNote->id, $creditNote->number, true, $creditNote->order_number,
                $creditNote->net_amount, $creditNote->vat_amount, $creditNote->total_amount, $creditNote->currency,
            ));

            return $creditNote->load('lines');
        });
    }
}
