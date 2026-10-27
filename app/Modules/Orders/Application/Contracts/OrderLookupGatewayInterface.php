<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Contracts;

use App\Modules\Orders\Application\Data\OrderSummaryData;

/**
 * Orders' public port for other modules (Shipping) that need to read an
 * order or nudge its status forward, without a SQL join or reaching into
 * Orders' Eloquent models (cahier §9.1).
 */
interface OrderLookupGatewayInterface
{
    /** Null when the order doesn't exist. */
    public function find(string $orderNumber): ?OrderSummaryData;

    /**
     * Best-effort: moves the order to "shipped" if that transition is
     * currently legal, false otherwise (e.g. already shipped by another
     * path, or still pending). Callers don't need to fail on a false
     * return — order status and shipment tracking are allowed to drift
     * slightly, this is just a convenience sync.
     */
    public function markShipped(string $orderNumber): bool;

    /**
     * Delivered orders (with their lines) whose delivery is at or before the
     * cutoff — i.e. past the return window.
     *
     * @return list<OrderSummaryData>
     */
    public function deliveredUntil(\DateTimeInterface $cutoff): array;

    /**
     * The most recent orders containing at least one line sold by the vendor.
     * Each summary carries ONLY that vendor's lines — never another vendor's
     * — so the result is safe to show to the vendor (cahier §8 cloisonnement).
     *
     * @return list<OrderSummaryData>
     */
    public function forVendor(int $vendorId, int $limit = 200): array;

    /** Same best-effort contract as markShipped(), for "delivered". */
    public function markDelivered(string $orderNumber): bool;
}
