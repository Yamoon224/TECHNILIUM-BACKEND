<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Application\Actions;

use App\Modules\Invoicing\Application\InvoiceNumberGenerator;
use App\Modules\Invoicing\Domain\Exceptions\InvoiceException;
use App\Modules\Invoicing\Domain\InvoiceType;
use App\Modules\Invoicing\Domain\VatBreakdown;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Shared\Events\InvoiceIssued;
use App\Modules\Shared\ValueObjects\Currency;
use App\Modules\Shared\ValueObjects\Money;
use App\Modules\Shared\ValueObjects\TauxTVA;
use Illuminate\Support\Facades\DB;

/**
 * Cancels an invoice (cahier §6.7: a validated invoice is never edited).
 * Without an amount the whole remaining balance is credited and the invoice
 * lines are mirrored; with an amount (a partial refund) a single-line credit
 * note is issued, its VAT split derived from the invoice's rate.
 */
final class IssueCreditNoteAction
{
    public function __construct(private readonly InvoiceNumberGenerator $numbers) {}

    public function execute(Invoice $invoice, string $reason, ?int $issuedBy = null, ?int $amount = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $reason, $issuedBy, $amount) {
            // Lock the invoice so concurrent credits are serialised and the
            // remaining balance below is read consistently.
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            $remaining = $invoice->remainingCreditable();

            if ($remaining <= 0) {
                throw InvoiceException::notCreditable($invoice->number);
            }

            $amount ??= $remaining;

            if ($amount > $remaining) {
                throw InvoiceException::amountExceedsRemaining($invoice->number, $remaining);
            }

            $isFull = $amount === $invoice->total_amount;

            if ($isFull) {
                [$net, $vat] = [$invoice->net_amount, $invoice->vat_amount];
            } else {
                $split = VatBreakdown::fromGross(
                    Money::ofMinorUnits($amount, Currency::from($invoice->currency)),
                    TauxTVA::ofPercent($invoice->vat_basis_points / 100),
                );
                [$net, $vat] = [$split->net->amount, $split->vat->amount];
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
                'net_amount' => $net,
                'vat_amount' => $vat,
                'total_amount' => $amount,
                'reason' => $reason,
                'issued_by' => $issuedBy,
                'issued_at' => $now,
            ]);

            if ($isFull) {
                foreach ($invoice->lines as $line) {
                    $creditNote->lines()->create($line->only([
                        'description', 'quantity', 'unit_price_amount', 'line_total_amount',
                    ]));
                }
            } else {
                $creditNote->lines()->create([
                    'description' => 'Avoir partiel - '.$reason,
                    'quantity' => 1,
                    'unit_price_amount' => $amount,
                    'line_total_amount' => $amount,
                ]);
            }

            event(new InvoiceIssued(
                $creditNote->id, $creditNote->number, true, $creditNote->order_number,
                $creditNote->net_amount, $creditNote->vat_amount, $creditNote->total_amount, $creditNote->currency,
            ));

            return $creditNote->load('lines');
        });
    }
}
