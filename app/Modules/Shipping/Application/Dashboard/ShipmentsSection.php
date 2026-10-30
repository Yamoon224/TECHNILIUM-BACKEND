<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Application\Dashboard;

use App\Modules\Shared\Dashboard\DashboardSection;
use App\Modules\Shared\Dashboard\Widgets;
use App\Modules\Shipping\Domain\ShipmentStatus;
use App\Modules\Shipping\Infrastructure\Models\Shipment;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final class ShipmentsSection implements DashboardSection
{
    /** @var array<string, array{0: string, 1: string}> */
    private const STATUS_LABELS = [
        'pending' => ['À expédier', 'To dispatch'],
        'in_transit' => ['En cours', 'In transit'],
        'delivered' => ['Livré', 'Delivered'],
        'failed_attempt' => ['Échec de livraison', 'Failed attempt'],
        'returned' => ['Retourné', 'Returned'],
    ];

    public function key(): string
    {
        return 'shipments';
    }

    public function ability(): string
    {
        return 'manage-shipments';
    }

    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        $toAssign = Shipment::query()->where('status', ShipmentStatus::Pending)->whereNull('assigned_to')->count();
        $inTransit = Shipment::query()->where('status', ShipmentStatus::InTransit)->count();
        $delivered = Shipment::query()
            ->where('status', ShipmentStatus::Delivered)
            ->whereBetween('delivered_at', [$from, $to])
            ->count();
        $failed = Shipment::query()->where('status', ShipmentStatus::FailedAttempt)->count();

        $byStatus = Shipment::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->status->value => (int) $row->total]);

        $points = [];
        foreach (self::STATUS_LABELS as $status => [$fr, $en]) {
            $points[] = ['label' => $fr, 'label_en' => $en, 'value' => (int) ($byStatus[$status] ?? 0)];
        }

        $rows = Shipment::query()
            ->where(fn (Builder $query) => $query
                ->where('status', ShipmentStatus::FailedAttempt)
                ->orWhere(fn (Builder $q) => $q->where('status', ShipmentStatus::Pending)->whereNull('assigned_to')))
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(8)
            ->get(['order_number', 'status', 'assigned_to', 'created_at'])
            ->map(fn (Shipment $shipment) => [
                $shipment->order_number,
                self::STATUS_LABELS[$shipment->status->value][0],
                $shipment->assigned_to === null ? 'Non assigné' : 'Livreur #'.$shipment->assigned_to,
                $shipment->created_at === null ? '' : (int) $shipment->created_at->diffInDays(now()).' j',
            ])
            ->all();

        return Widgets::section(
            'Livraisons',
            'Deliveries',
            '/livraisons',
            [
                Widgets::kpi('to_assign', 'À assigner', 'To assign', $toAssign, Widgets::NUMBER, $toAssign > 0 ? Widgets::WARN : Widgets::GOOD),
                Widgets::kpi('in_transit', 'En cours', 'In transit', $inTransit),
                Widgets::kpi('delivered', 'Livrées (période)', 'Delivered (period)', $delivered, Widgets::NUMBER, Widgets::GOOD),
                Widgets::kpi('failed', 'Échecs de livraison', 'Failed deliveries', $failed, Widgets::NUMBER, $failed > 0 ? Widgets::BAD : Widgets::GOOD),
            ],
            [Widgets::chart('by_status', 'Colis par statut', 'Parcels by status', 'bar', $points)],
            [Widgets::table(
                'attention',
                'Colis à traiter',
                'Parcels needing attention',
                [['Commande', 'Order'], ['Statut', 'Status'], ['Livreur', 'Courier'], ['Ancienneté', 'Age']],
                $rows,
            )],
        );
    }
}
