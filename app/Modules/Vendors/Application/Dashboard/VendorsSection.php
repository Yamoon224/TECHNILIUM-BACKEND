<?php

declare(strict_types=1);

namespace App\Modules\Vendors\Application\Dashboard;

use App\Modules\Shared\Dashboard\DashboardSection;
use App\Modules\Shared\Dashboard\Widgets;
use App\Modules\Vendors\Domain\VendorStatus;
use App\Modules\Vendors\Infrastructure\Models\Vendor;
use Carbon\CarbonInterface;

/**
 * Vendors block of the back-office home page: onboarding pipeline (current state).
 */
final class VendorsSection implements DashboardSection
{
    public function key(): string
    {
        return 'vendors';
    }

    public function ability(): string
    {
        return 'manage-vendors';
    }

    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        /** @var array<string, int> $byStatus */
        $byStatus = Vendor::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($n) => (int) $n)
            ->all();

        $active = $byStatus[VendorStatus::Active->value] ?? 0;
        $pending = $byStatus[VendorStatus::Pending->value] ?? 0;
        $suspended = $byStatus[VendorStatus::Suspended->value] ?? 0;

        $labels = [
            VendorStatus::Active->value => 'Actif',
            VendorStatus::Pending->value => 'En attente',
            VendorStatus::Suspended->value => 'Suspendu',
        ];

        $rows = Vendor::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (Vendor $v) => [
                $v->company_name,
                $labels[$v->status->value],
                $v->created_at?->format('d/m/Y'),
            ])->all();

        return Widgets::section(
            'Vendeurs',
            'Vendors',
            '/vendeurs',
            [
                Widgets::kpi('vendors_active', 'Vendeurs actifs', 'Active vendors', $active, Widgets::NUMBER, Widgets::GOOD),
                Widgets::kpi('vendors_pending', 'En attente de validation', 'Awaiting approval', $pending, Widgets::NUMBER, $pending > 0 ? Widgets::WARN : null),
                Widgets::kpi('vendors_suspended', 'Vendeurs suspendus', 'Suspended vendors', $suspended),
            ],
            [
                Widgets::chart('vendors_by_status', 'Vendeurs par statut', 'Vendors by status', 'donut', [
                    ['label' => 'Actifs', 'label_en' => 'Active', 'value' => $active],
                    ['label' => 'En attente', 'label_en' => 'Pending', 'value' => $pending],
                    ['label' => 'Suspendus', 'label_en' => 'Suspended', 'value' => $suspended],
                ]),
            ],
            [
                Widgets::table('vendors_latest', 'Derniers vendeurs inscrits', 'Latest vendors', [
                    ['Société', 'Company'],
                    ['Statut', 'Status'],
                    ['Inscrit le', 'Registered'],
                ], $rows),
            ],
        );
    }
}
