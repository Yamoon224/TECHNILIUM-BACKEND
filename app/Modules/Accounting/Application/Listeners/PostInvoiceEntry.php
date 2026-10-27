<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Listeners;

use App\Modules\Accounting\Application\Actions\PostJournalEntryAction;
use App\Modules\Accounting\Domain\PostingSchemes;
use App\Modules\Shared\Events\InvoiceIssued;

final class PostInvoiceEntry
{
    public function __construct(private readonly PostJournalEntryAction $post) {}

    public function handle(InvoiceIssued $event): void
    {
        $this->post->execute(
            journal: 'VT',
            sourceType: 'invoice',
            sourceId: $event->invoiceId,
            description: ($event->isCreditNote ? 'Avoir ' : 'Facture ').$event->number,
            lines: PostingSchemes::invoice($event->netAmount, $event->vatAmount, $event->totalAmount, $event->isCreditNote),
            currency: $event->currency,
            reference: $event->orderNumber,
        );
    }
}
