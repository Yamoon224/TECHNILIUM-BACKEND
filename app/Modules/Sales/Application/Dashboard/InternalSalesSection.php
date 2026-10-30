<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application\Dashboard;

use App\Modules\Sales\Infrastructure\Models\CashClosing;
use App\Modules\Sales\Infrastructure\Models\InternalSale;
use App\Modules\Shared\Dashboard\DashboardSection;
use App\Modules\Shared\Dashboard\Widgets;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class InternalSalesSection implements DashboardSection
{
    private const MAX_POINTS = 31;

    public function key(): string
    {
        return 'internal-sales';
    }

    public function ability(): string
    {
        return 'manage-internal-sales';
    }

    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        $period = $this->totals($from, $to);
        $today = $this->totals(now()->startOfDay(), now()->endOfDay());

        return Widgets::section(
            'Ventes internes (caisse)',
            'Internal sales (till)',
            '/ventes-internes',
            [
                Widgets::kpi('period_count', 'Ventes de la période', 'Sales in period', $period['count']),
                Widgets::kpi('period_amount', 'Montant de la période', 'Amount in period', $period['amount'], Widgets::MONEY),
                Widgets::kpi('today_count', 'Ventes du jour', 'Sales today', $today['count']),
                Widgets::kpi('today_amount', 'Montant du jour', 'Amount today', $today['amount'], Widgets::MONEY),
            ],
            [Widgets::chart('amount_by_day', 'Montant des ventes par jour', 'Sales amount per day', 'bar', $this->dailyPoints($from, $to), Widgets::MONEY)],
            [Widgets::table(
                'by_cashier',
                'Top caissiers',
                'Top cashiers',
                [['Caissier', 'Cashier'], ['Ventes', 'Sales'], ['Montant', 'Amount']],
                $this->topCashiers($from, $to),
            )],
        );
    }

    /**
     * @return array{count: int, amount: int}
     */
    private function totals(CarbonInterface $from, CarbonInterface $to): array
    {
        $row = InternalSale::query()
            ->whereNull('cancelled_at')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(amount), 0) as amount')
            ->first();

        return ['count' => (int) ($row->total ?? 0), 'amount' => (int) ($row->amount ?? 0)];
    }

    /**
     * One point per day, or per block of days when the period is longer than 31 days.
     *
     * @return list<array{label: string, value: int}>
     */
    private function dailyPoints(CarbonInterface $from, CarbonInterface $to): array
    {
        $start = CarbonImmutable::instance($from)->startOfDay();
        $days = (int) $start->diffInDays(CarbonImmutable::instance($to)->startOfDay()) + 1;
        if ($days < 1) {
            return [];
        }

        $perDay = InternalSale::query()
            ->whereNull('cancelled_at')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as day, SUM(amount) as amount')
            ->groupBy('day')
            ->pluck('amount', 'day');

        $size = (int) ceil($days / self::MAX_POINTS);
        $points = [];
        for ($offset = 0; $offset < $days; $offset += $size) {
            $total = 0;
            for ($i = $offset; $i < min($offset + $size, $days); $i++) {
                $total += (int) ($perDay[$start->addDays($i)->toDateString()] ?? 0);
            }
            $points[] = ['label' => $start->addDays($offset)->format('d/m'), 'value' => $total];
        }

        return $points;
    }

    /**
     * @return list<list<string|int>>
     */
    private function topCashiers(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = InternalSale::query()
            ->whereNull('cancelled_at')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('seller_id, COUNT(*) as total, SUM(amount) as amount')
            ->groupBy('seller_id')
            ->orderByDesc('amount')
            ->limit(5)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        // Identity owns the accounts; the till only knows the name snapshotted on a closing.
        $names = CashClosing::query()
            ->whereIn('user_id', $rows->pluck('seller_id'))
            ->orderBy('id')
            ->pluck('user_name', 'user_id');

        return $rows->map(fn ($row) => [
            (string) ($names[$row->seller_id] ?? 'Caissier #'.$row->seller_id),
            (int) $row->total,
            number_format((int) $row->amount, 0, ',', ' ').' XOF',
        ])->all();
    }
}
