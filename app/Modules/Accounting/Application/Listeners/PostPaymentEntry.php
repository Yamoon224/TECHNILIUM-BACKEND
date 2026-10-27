<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Listeners;

use App\Modules\Accounting\Application\Actions\PostJournalEntryAction;
use App\Modules\Accounting\Domain\PostingSchemes;
use App\Modules\Shared\Events\PaymentSettled;

final class PostPaymentEntry
{
    public function __construct(private readonly PostJournalEntryAction $post) {}

    public function handle(PaymentSettled $event): void
    {
        $this->post->execute(
            journal: PostingSchemes::paymentJournal($event->method),
            // A refund is its own ledger event, distinct from the collection.
            sourceType: $event->refunded ? 'payment_refund' : 'payment',
            sourceId: $event->refunded ? (int) $event->refundId : $event->paymentId,
            description: ($event->refunded ? 'Remboursement' : 'Encaissement').' commande '.$event->orderNumber,
            lines: PostingSchemes::payment($event->method, $event->amount, $event->refunded),
            currency: $event->currency,
            reference: $event->orderNumber,
        );
    }
}
