<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events;

/**
 * Published by Payments when a payment is confirmed or refunded; Accounting
 * records the cash movement. $refunded = true reverses the collection.
 */
final readonly class PaymentSettled implements DomainEvent
{
    public function __construct(
        public int $paymentId,
        public string $orderNumber,
        public string $method,
        public int $amount,
        public string $currency,
        public bool $refunded,
    ) {}
}
