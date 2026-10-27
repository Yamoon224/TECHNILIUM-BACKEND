<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events;

/**
 * Published by Payouts when a vendor payout is calculated: the vendor's
 * gross share leaves sales revenue, Technilium keeps the commission and owes
 * the net to the vendor. Accounting turns it into entries.
 */
final readonly class PayoutCalculated implements DomainEvent
{
    public function __construct(
        public int $payoutId,
        public int $vendorId,
        public int $grossAmount,
        public int $commissionAmount,
        public int $netAmount,
        public string $currency,
    ) {}
}
