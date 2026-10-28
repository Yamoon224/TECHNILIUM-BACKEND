<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events;

/**
 * Published by Returns when the support moves a return forward without money
 * changing hands: accepted, parcel received, or rejected (a refund has its own
 * event, ReturnRefunded). Notifications tells the customer.
 */
final readonly class ReturnDecided implements DomainEvent
{
    public function __construct(
        public int $returnId,
        public string $orderNumber,
        /** accepted | received | rejected */
        public string $status,
        /** What the support wrote for the customer, if anything. */
        public ?string $note,
    ) {}
}
