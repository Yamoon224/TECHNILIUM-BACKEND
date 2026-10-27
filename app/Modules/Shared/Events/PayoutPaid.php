<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events;

/** Published by Payouts when the transfer to the vendor has been executed. */
final readonly class PayoutPaid implements DomainEvent
{
    public function __construct(
        public int $payoutId,
        public int $vendorId,
        public int $netAmount,
        public string $currency,
    ) {}
}
