<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Listeners;

use App\Modules\Accounting\Application\Actions\PostJournalEntryAction;
use App\Modules\Accounting\Domain\PostingSchemes;
use App\Modules\Shared\Events\PayoutCalculated;
use App\Modules\Shared\Events\PayoutPaid;

final class PostPayoutEntry
{
    public function __construct(private readonly PostJournalEntryAction $post) {}

    public function handleCalculated(PayoutCalculated $event): void
    {
        $this->post->execute(
            journal: 'OD',
            sourceType: 'payout',
            sourceId: $event->payoutId,
            description: "Reversement vendeur {$event->vendorId} : commission et dette",
            lines: PostingSchemes::payoutCalculated($event->grossAmount, $event->commissionAmount, $event->netAmount),
            currency: $event->currency,
            reference: "PAYOUT-{$event->payoutId}",
        );
    }

    public function handlePaid(PayoutPaid $event): void
    {
        $this->post->execute(
            journal: 'MM',
            sourceType: 'payout_paid',
            sourceId: $event->payoutId,
            description: "Règlement du reversement {$event->payoutId} au vendeur {$event->vendorId}",
            lines: PostingSchemes::payoutPaid($event->netAmount),
            currency: $event->currency,
            reference: "PAYOUT-{$event->payoutId}",
        );
    }
}
