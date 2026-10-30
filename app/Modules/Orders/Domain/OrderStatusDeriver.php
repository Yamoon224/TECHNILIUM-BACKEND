<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain;

/**
 * Derives the parent order status from its sub-orders (cahier §3.2): the
 * parent is only as advanced as its least advanced live sub-order, and is
 * cancelled only once every sub-order is. Pure, so it is unit-tested alone.
 */
final class OrderStatusDeriver
{
    private const PROGRESS = [
        'pending' => 0,
        'confirmed' => 1,
        'shipped' => 2,
        'delivered' => 3,
    ];

    /**
     * @param  list<OrderStatus>  $subOrderStatuses
     */
    public static function derive(array $subOrderStatuses): ?OrderStatus
    {
        if ($subOrderStatuses === []) {
            return null;
        }

        $live = array_values(array_filter($subOrderStatuses, fn (OrderStatus $s) => $s !== OrderStatus::Cancelled));

        if ($live === []) {
            return OrderStatus::Cancelled;
        }

        usort($live, fn (OrderStatus $a, OrderStatus $b) => self::PROGRESS[$a->value] <=> self::PROGRESS[$b->value]);

        return $live[0];
    }

    /**
     * True when live sub-orders are at different stages - shown to the
     * customer as "partiellement expédiée / livrée".
     *
     * @param  list<OrderStatus>  $subOrderStatuses
     */
    public static function isPartial(array $subOrderStatuses): bool
    {
        $live = array_filter($subOrderStatuses, fn (OrderStatus $s) => $s !== OrderStatus::Cancelled);

        return count(array_unique(array_map(fn (OrderStatus $s) => $s->value, $live))) > 1;
    }
}
