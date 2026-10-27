<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Data;

/**
 * Orders' public, read-only view of an order — the only shape other
 * modules (Shipping) are allowed to depend on. Never expose
 * Infrastructure\Models\Order outside this module (cahier §9.1).
 */
final readonly class OrderSummaryData
{
    public function __construct(
        public string $orderNumber,
        public string $customerName,
        public string $customerPhone,
        public string $deliveryAddress,
        public ?string $deliveryCity,
        public string $status,
        public string $currency = 'XOF',
        public int $subtotalAmount = 0,
        /** @var list<OrderLineData> */
        public array $lines = [],
        /** ISO-8601 delivery time, null until delivered. */
        public ?string $deliveredAt = null,
        /** Coupon discount already taken off subtotalAmount (borne by Technilium). */
        public int $discountAmount = 0,
        public ?string $couponCode = null,
        /** The customer account the order belongs to; null for a guest order. */
        public ?int $customerId = null,
    ) {}
}
