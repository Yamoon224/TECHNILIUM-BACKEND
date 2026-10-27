<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events;

/** Published by Procurement when a supplier invoice is (partly) paid. */
final readonly class SupplierPaid implements DomainEvent
{
    public function __construct(
        public int $paymentId,
        public string $invoiceNumber,
        /** "goods" or "landed_cost": decides which payable account is settled. */
        public string $kind,
        public int $amount,
        public string $currency,
    ) {}
}
