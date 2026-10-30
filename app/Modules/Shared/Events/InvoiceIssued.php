<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events;

/**
 * Published by Invoicing when an invoice or credit note is issued; Accounting
 * turns it into journal entries (cahier §10.2). Amounts are positive - a
 * credit note is flagged, not negated.
 */
final readonly class InvoiceIssued implements DomainEvent
{
    public function __construct(
        public int $invoiceId,
        public string $number,
        public bool $isCreditNote,
        public string $orderNumber,
        public int $netAmount,
        public int $vatAmount,
        public int $totalAmount,
        public string $currency,
    ) {}
}
