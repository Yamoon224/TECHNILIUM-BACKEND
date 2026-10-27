<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Application\Listeners;

use App\Modules\Invoicing\Application\Actions\IssueInvoiceAction;
use App\Modules\Invoicing\Domain\Exceptions\InvoiceException;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Shared\Events\OrderStatusChanged;
use Throwable;

/**
 * Invoices a sale as soon as it is confirmed, so the customer finds the
 * document in their account without waiting for the accountant.
 *
 * Only an order that was never invoiced is touched: once a document exists —
 * even one since cancelled by a credit note — a corrected invoice stays the
 * accountant's decision. Switchable with INVOICING_AUTO_ISSUE.
 *
 * Side effect: it must never block the order's progress, so failures are
 * reported, not thrown (the accountant can still issue by hand).
 */
final class IssueInvoiceOnConfirmation
{
    /** Same set as the manual path: a sale, not a pending or cancelled order. */
    private const INVOICEABLE = ['confirmed', 'shipped', 'delivered'];

    public function __construct(private readonly IssueInvoiceAction $invoices) {}

    public function handle(OrderStatusChanged $event): void
    {
        if (! config('invoicing.auto_issue') || ! in_array($event->status, self::INVOICEABLE, true)) {
            return;
        }

        if (Invoice::query()->where('order_number', $event->orderNumber)->exists()) {
            return;
        }

        try {
            $this->invoices->execute($event->orderNumber);
        } catch (InvoiceException) {
            // Not invoiceable right now; nothing to do.
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
