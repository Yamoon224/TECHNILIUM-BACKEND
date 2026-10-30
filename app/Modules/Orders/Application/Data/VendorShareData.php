<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Data;

/**
 * One vendor's delivered share of an order - the unit Payouts pays
 * (cahier §6.8). Public, read-only; never expose the Eloquent models.
 */
final readonly class VendorShareData
{
    public function __construct(
        public string $orderNumber,
        public int $vendorId,
        public string $currency,
        /** Sum of the vendor's lines, VAT-inclusive. */
        public int $grossAmount,
        /** Whole-order subtotal, the base refunds are prorated on. */
        public int $orderSubtotalAmount,
    ) {}
}
