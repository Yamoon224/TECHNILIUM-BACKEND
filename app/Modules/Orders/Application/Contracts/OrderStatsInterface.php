<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Contracts;

use DateTimeInterface;

/**
 * Orders' public read-model for Reporting (cahier §6.14). Figures exclude
 * cancelled orders unless stated; amounts are VAT-inclusive minor units.
 */
interface OrderStatsInterface
{
    /**
     * Sales per calendar day in [from, to], oldest first.
     *
     * @return list<array{date: string, orders: int, revenue: int}>
     */
    public function salesByDay(DateTimeInterface $from, DateTimeInterface $to): array;

    /**
     * Orders placed in [from, to] per status (cancelled included).
     *
     * @return array<string, int>
     */
    public function ordersByStatus(DateTimeInterface $from, DateTimeInterface $to): array;

    /**
     * Best-selling products by revenue in [from, to].
     *
     * @return list<array{product: string, quantity: int, revenue: int}>
     */
    public function topProducts(DateTimeInterface $from, DateTimeInterface $to, int $limit): array;

    /**
     * Revenue per seller in [from, to], by sub-order; vendor_id null = Technilium's own stock.
     *
     * @return list<array{vendor_id: int|null, orders: int, revenue: int}>
     */
    public function revenueBySeller(DateTimeInterface $from, DateTimeInterface $to): array;

    /** Orders still pending or confirmed that were placed before the given moment. */
    public function countStalledBefore(DateTimeInterface $moment): int;
}
