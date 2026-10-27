<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events;

/**
 * Published by Orders each time an order's (derived) status actually
 * changes — never on a no-op — so the customer is told once per step.
 */
final readonly class OrderStatusChanged implements DomainEvent
{
    public function __construct(
        public string $orderNumber,
        public string $customerName,
        public string $customerPhone,
        public string $status,
        /** Lets a consumer record the change idempotently (0 when unknown). */
        public int $orderId = 0,
        public ?string $customerEmail = null,
    ) {}
}
