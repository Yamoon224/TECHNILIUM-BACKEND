<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events;

/** Published by Orders when a checkout succeeds; Notifications tells the customer. */
final readonly class OrderPlaced implements DomainEvent
{
    public function __construct(
        public string $orderNumber,
        public string $customerName,
        public string $customerPhone,
        public int $totalAmount,
        public string $currency,
        /** The delivery confirmation code the customer must keep (cahier §5.6). */
        public ?string $deliveryCode,
    ) {}
}
