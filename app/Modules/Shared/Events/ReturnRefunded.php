<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events;

/**
 * Published by Returns when a return is refunded. Invoicing answers with a
 * (partial) credit note on the order's live invoice.
 */
final readonly class ReturnRefunded implements DomainEvent
{
    public function __construct(
        public int $returnId,
        public string $orderNumber,
        public int $amount,
        public string $currency,
        public string $reason,
    ) {}
}
