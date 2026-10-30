<?php

declare(strict_types=1);

namespace App\Modules\Payouts\Application\Dashboard;

use App\Modules\Payouts\Domain\PayoutStatus;
use App\Modules\Payouts\Infrastructure\Models\Payout;
use App\Modules\Shared\Dashboard\DashboardSection;
use App\Modules\Shared\Dashboard\Widgets;
use Carbon\CarbonInterface;

/**
 * Dashboard block of the payouts feature: what is owed to vendors, the
 * commissions earned and paid out in the period, and the payouts left to pay.
 */
final class PayoutsSection implements DashboardSection
{
    public function key(): string
    {
        return 'payouts';
    }

    public function ability(): string
    {
        return 'manage-payouts';
    }

    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        $pending = Payout::query()
            ->where('status', PayoutStatus::Pending->value)
            ->selectRaw('count(*) as total, coalesce(sum(net_amount), 0) as amount')
            ->toBase()
            ->first();

        $owed = (int) ($pending->amount ?? 0);

        $commissions = (int) Payout::query()->whereBetween('created_at', [$from, $to])->sum('commission_amount');

        $paid = (int) Payout::query()
            ->where('status', PayoutStatus::Paid->value)
            ->whereBetween('paid_at', [$from, $to])
            ->sum('net_amount');

        $toPay = Payout::query()
            ->where('status', PayoutStatus::Pending->value)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(8)
            ->get()
            ->map(fn (Payout $payout) => [
                $payout->vendor_name,
                number_format($payout->net_amount, 0, ',', ' ').' '.$payout->currency,
                $payout->created_at?->format('d/m/Y'),
                'En attente',
            ])
            ->all();

        return Widgets::section(
            'Reversements',
            'Payouts',
            '/reversements',
            [
                Widgets::kpi('owed', 'Dû aux vendeurs', 'Owed to vendors', $owed, Widgets::MONEY, $owed > 0 ? Widgets::WARN : null),
                Widgets::kpi('commissions', 'Commissions de la période', 'Commissions in the period', $commissions, Widgets::MONEY),
                Widgets::kpi('paid', 'Versé sur la période', 'Paid in the period', $paid, Widgets::MONEY),
                Widgets::kpi('pending', 'Reversements en attente', 'Payouts awaiting payment', (int) ($pending->total ?? 0)),
            ],
            [],
            [
                Widgets::table('to_pay', 'Reversements à payer', 'Payouts to pay', [
                    ['Vendeur', 'Vendor'], ['Montant net', 'Net amount'], ['Calculé le', 'Calculated on'], ['Statut', 'Status'],
                ], $toPay),
            ],
        );
    }
}
