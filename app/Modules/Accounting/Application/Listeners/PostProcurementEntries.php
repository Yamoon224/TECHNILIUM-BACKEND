<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Listeners;

use App\Modules\Accounting\Application\Actions\PostJournalEntryAction;
use App\Modules\Accounting\Domain\PostingSchemes;
use App\Modules\Shared\Events\PurchaseReceived;
use App\Modules\Shared\Events\StockConsumed;
use App\Modules\Shared\Events\SupplierPaid;

/** Books the purchase cycle: stock in, supplier paid, cost of goods sold. */
final class PostProcurementEntries
{
    public function __construct(private readonly PostJournalEntryAction $post) {}

    public function handleReceived(PurchaseReceived $event): void
    {
        // A zero-cost receipt (free goods) has nothing to book.
        if ($event->goodsAmount + $event->landedAmount <= 0) {
            return;
        }

        $this->post->execute(
            journal: 'AC',
            sourceType: 'purchase_receipt',
            sourceId: $event->receiptId,
            description: "Réception marchandises {$event->purchaseNumber}",
            lines: PostingSchemes::purchaseReceived($event->goodsAmount, $event->landedAmount),
            currency: $event->currency,
            reference: $event->purchaseNumber,
        );
    }

    public function handlePaid(SupplierPaid $event): void
    {
        $this->post->execute(
            journal: 'BQ',
            sourceType: 'supplier_payment',
            sourceId: $event->paymentId,
            description: "Règlement fournisseur, facture {$event->invoiceNumber}",
            lines: PostingSchemes::supplierPaid($event->kind, $event->amount),
            currency: $event->currency,
            reference: $event->invoiceNumber,
        );
    }

    public function handleConsumed(StockConsumed $event): void
    {
        $this->post->execute(
            journal: 'OD',
            sourceType: 'cogs',
            sourceId: $event->orderId,
            description: "Coût des marchandises vendues, commande {$event->orderNumber}",
            lines: PostingSchemes::costOfGoodsSold($event->costAmount),
            currency: $event->currency,
            reference: $event->orderNumber,
        );
    }
}
