<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Dashboard;

use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Shared\Dashboard\DashboardSection;
use App\Modules\Shared\Dashboard\Widgets;
use Carbon\CarbonInterface;

final class OrdersSection implements DashboardSection
{
    /** @var array<string, array{0: string, 1: string}> */
    private const STATUS_LABELS = [
        'pending' => ['Nouvelle', 'New'],
        'confirmed' => ['Confirmée', 'Confirmed'],
        'shipped' => ['Expédiée', 'Shipped'],
        'delivered' => ['Livrée', 'Delivered'],
        'cancelled' => ['Annulée', 'Cancelled'],
    ];

    public function key(): string
    {
        return 'orders';
    }

    public function ability(): string
    {
        return 'manage-orders';
    }

    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        $toProcess = [OrderStatus::Pending, OrderStatus::Confirmed];

        $periodCount = Order::query()->whereBetween('created_at', [$from, $to])->count();
        $todayCount = Order::query()->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])->count();
        $toProcessCount = Order::query()->whereIn('status', $toProcess)->count();
        $stalled = Order::query()
            ->whereIn('status', $toProcess)
            ->where('created_at', '<', now()->subDays((int) config('reporting.stalled_after_days')))
            ->count();

        $byStatus = Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->status->value => (int) $row->total]);

        $points = [];
        foreach (self::STATUS_LABELS as $status => [$fr, $en]) {
            $count = (int) ($byStatus[$status] ?? 0);
            if ($count > 0) {
                $points[] = ['label' => $fr, 'label_en' => $en, 'value' => $count];
            }
        }

        $rows = Order::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['order_number', 'customer_name', 'subtotal_amount', 'status', 'created_at'])
            ->map(fn (Order $order) => [
                $order->order_number,
                $order->customer_name,
                number_format($order->subtotal_amount, 0, ',', ' ').' XOF',
                self::STATUS_LABELS[$order->status->value][0],
                $order->created_at?->format('d/m/Y H:i'),
            ])
            ->all();

        return Widgets::section(
            'Commandes',
            'Orders',
            '/commandes',
            [
                Widgets::kpi('period', 'Commandes de la période', 'Orders in period', $periodCount),
                Widgets::kpi('today', 'Commandes du jour', 'Orders today', $todayCount),
                Widgets::kpi('to_process', 'À traiter', 'To process', $toProcessCount, Widgets::NUMBER, $toProcessCount > 0 ? Widgets::WARN : Widgets::GOOD),
                Widgets::kpi('stalled', 'Commandes bloquées', 'Stalled orders', $stalled, Widgets::NUMBER, $stalled > 0 ? Widgets::BAD : Widgets::GOOD),
            ],
            [Widgets::chart('by_status', 'Commandes par statut', 'Orders by status', 'donut', $points)],
            [Widgets::table(
                'latest',
                'Dernières commandes',
                'Latest orders',
                [['N°', 'No.'], ['Client', 'Customer'], ['Total', 'Total'], ['Statut', 'Status'], ['Date', 'Date']],
                $rows,
            )],
        );
    }
}
