<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Contracts;

use App\Modules\Orders\Application\Data\OrderSummaryData;
use App\Modules\Orders\Application\Data\VendorShareData;

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
     * return - order status and shipment tracking are allowed to drift
     * slightly, this is just a convenience sync.
     */
    public function markShipped(string $orderNumber): bool;

    /**
     * A vendor's shares that were delivered at or before the cutoff - i.e.
     * past the return window. Sub-orders are the unit; an order placed
     * before sub-orders existed is read from its lines once it is delivered.
     *
     * @return list<VendorShareData>
     */
    public function deliveredVendorSharesUntil(int $vendorId, \DateTimeInterface $cutoff): array;

    /**
     * The most recent orders containing at least one line sold by the vendor.
     * Each summary carries ONLY that vendor's lines - never another vendor's
     * - and the status / delivery date of that vendor's own sub-order, so the
     * result is safe to show to the vendor (cahier §8 cloisonnement).
     *
     * @return list<OrderSummaryData>
     */
    public function forVendor(int $vendorId, int $limit = 200): array;

    /**
     * The vendor ships its own share (confirmed → shipped). False when the
     * vendor has no sub-order on that order or the move is not legal now.
     */
    public function shipForVendor(string $orderNumber, int $vendorId): bool;

    /**
     * What a customer has actually received: every offer in one of their
     * delivered orders, mapped to the (first) order that delivered it. A
     * guest order has no account and never appears here.
     *
     * @return array<int, string>  offer id => order number
     */
    public function deliveredOffersForCustomer(int $customerId): array;

    /**
     * Checks the customer's delivery confirmation code (constant-time). An
     * order placed before codes existed has none and is accepted as is; an
     * unknown order is refused.
     */
    public function confirmDeliveryCode(string $orderNumber, string $code): bool;

    /**
     * The most recent counter sales (channel "internal"), newest first.
     *
     * @return list<OrderSummaryData>
     */
    public function recentInternalSales(int $limit = 50): array;

    /** Same best-effort contract as markShipped(), for "delivered". */
    public function markDelivered(string $orderNumber): bool;
}
