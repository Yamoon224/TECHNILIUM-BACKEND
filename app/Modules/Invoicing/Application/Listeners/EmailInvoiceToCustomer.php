<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Application\Listeners;

use App\Modules\Invoicing\Application\RenderInvoiceDocument;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface;
use App\Modules\Shared\Events\InvoiceIssued;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends the customer their invoice (or credit note) as a PDF attachment, to
 * the optional e-mail given at checkout. Runs only once the document is
 * committed, so a rolled-back invoice is never mailed.
 *
 * Side effect: it must never block issuing the document, so failures are
 * reported, not thrown. INVOICING_EMAIL_DRIVER=log (the default) sends nothing
 * and only notes the delivery in the log; "mail" sends through the MAIL_* settings.
 */
final class EmailInvoiceToCustomer implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly OrderLookupGatewayInterface $orders,
        private readonly RenderInvoiceDocument $document,
    ) {}

    public function handle(InvoiceIssued $event): void
    {
        try {
            $email = trim((string) $this->orders->find($event->orderNumber)?->customerEmail);

            if ($email === '') {
                return;
            }

            $invoice = Invoice::query()->with('lines')->find($event->invoiceId);

            if ($invoice === null) {
                return;
            }

            $driver = config('invoicing.email_driver');

            if ($driver !== 'mail') {
                Log::info('Envoi de facture simulé', ['number' => $invoice->number, 'recipient' => self::mask($email)]);

                return;
            }

            $label = $event->isCreditNote ? 'avoir' : 'facture';
            $body = sprintf(
                "Bonjour %s,\n\nVous trouverez en pièce jointe votre %s %s (commande %s).\n\nMerci de votre confiance,\nTechnilium",
                $invoice->customer_name,
                $label,
                $invoice->number,
                $invoice->order_number,
            );
            $pdf = $this->document->pdf($invoice);
            $filename = $this->document->filename($invoice);

            Mail::send([], [], function (Message $message) use ($email, $invoice, $label, $body, $pdf, $filename): void {
                $message->to($email)
                    ->subject(sprintf('%s — votre %s %s', config('app.name'), $label, $invoice->number))
                    ->text($body)
                    ->attachData($pdf, $filename, ['mime' => 'application/pdf']);
            });
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** "awa@example.com" -> "aw***@example.com": the log keeps no full personal address. */
    private static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return substr($local, 0, 2).str_repeat('*', max(0, strlen($local) - 2)).($domain !== '' ? '@'.$domain : '');
    }
}
