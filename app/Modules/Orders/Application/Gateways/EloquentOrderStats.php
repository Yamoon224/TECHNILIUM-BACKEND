<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Gateways;

use App\Modules\Orders\Application\Contracts\OrderStatsInterface;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use App\Modules\Orders\Infrastructure\Models\SubOrder;
use DateTimeInterface;

final class EloquentOrderStats implements OrderStatsInterface
{
    public function salesByDay(DateTimeInterface $from, DateTimeInterface $to): array
    {
        return Order::query()
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as orders, SUM(subtotal_amount) as revenue')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => [
                'date' => (string) $row->day,
                'orders' => (int) $row->orders,
                'revenue' => (int) $row->revenue,
            ])
            ->all();
    }

    public function ordersByStatus(DateTimeInterface $from, DateTimeInterface $to): array
    {
        return Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->status->value => (int) $row->total])
            ->all();
    }

    public function topProducts(DateTimeInterface $from, DateTimeInterface $to, int $limit): array
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->whereBetween('orders.created_at', [$from, $to])
            ->selectRaw('order_items.brand as brand, order_items.product_name as name, SUM(order_items.quantity) as quantity, SUM(order_items.line_total_amount) as revenue')
            ->groupBy('order_items.brand', 'order_items.product_name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'product' => trim($row->brand.' '.$row->name),
                'quantity' => (int) $row->quantity,
                'revenue' => (int) $row->revenue,
            ])
            ->all();
    }

    public function revenueBySeller(DateTimeInterface $from, DateTimeInterface $to): array
    {
        return SubOrder::query()
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('vendor_id, COUNT(*) as orders, SUM(subtotal_amount) as revenue')
            ->groupBy('vendor_id')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'vendor_id' => $row->vendor_id === null ? null : (int) $row->vendor_id,
                'orders' => (int) $row->orders,
                'revenue' => (int) $row->revenue,
            ])
            ->all();
    }

    public function countStalledBefore(DateTimeInterface $moment): int
    {
        return Order::query()
            ->whereIn('status', [OrderStatus::Pending, OrderStatus::Confirmed])
            ->where('created_at', '<', $moment)
            ->count();
    }
}
