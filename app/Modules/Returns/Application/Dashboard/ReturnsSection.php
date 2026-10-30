<?php

declare(strict_types=1);

namespace App\Modules\Returns\Application\Dashboard;

use App\Modules\Returns\Domain\ReturnStatus;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use App\Modules\Shared\Dashboard\DashboardSection;
use App\Modules\Shared\Dashboard\Widgets;
use Carbon\CarbonInterface;

final class ReturnsSection implements DashboardSection
{
    /** @var array<string, array{0: string, 1: string}> */
    private const STATUS_LABELS = [
        'requested' => ['Demandé', 'Requested'],
        'accepted' => ['Accepté', 'Accepted'],
        'received' => ['Reçu', 'Received'],
        'refunded' => ['Remboursé', 'Refunded'],
        'rejected' => ['Refusé', 'Rejected'],
    ];

    public function key(): string
    {
        return 'returns';
    }

    public function ability(): string
    {
        return 'manage-returns';
    }

    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        $open = [ReturnStatus::Requested, ReturnStatus::Accepted, ReturnStatus::Received];

        $openCount = ReturnRequest::query()->whereIn('status', $open)->count();
        $toDecide = ReturnRequest::query()->where('status', ReturnStatus::Received)->count();
        $refunded = ReturnRequest::query()
            ->where('status', ReturnStatus::Refunded)
            ->whereBetween('refunded_at', [$from, $to])
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(refunded_amount), 0) as amount')
            ->first();

        $byStatus = ReturnRequest::query()
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

        $rows = ReturnRequest::query()
            ->whereIn('status', $open)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(8)
            ->get(['id', 'order_number', 'status', 'reason', 'requested_amount', 'created_at'])
            ->map(fn (ReturnRequest $return) => [
                $return->number(),
                $return->order_number,
                self::STATUS_LABELS[$return->status->value][0],
                number_format($return->requested_amount, 0, ',', ' ').' XOF',
                $return->created_at?->format('d/m/Y'),
            ])
            ->all();

        return Widgets::section(
            'Retours',
            'Returns',
            '/retours',
            [
                Widgets::kpi('open', 'Retours à traiter', 'Open returns', $openCount, Widgets::NUMBER, $openCount > 0 ? Widgets::WARN : Widgets::GOOD),
                Widgets::kpi('to_decide', 'Reçus à décider', 'Received, to decide', $toDecide, Widgets::NUMBER, $toDecide > 0 ? Widgets::BAD : Widgets::GOOD),
                Widgets::kpi('refunded_count', 'Remboursés (période)', 'Refunded (period)', (int) ($refunded->total ?? 0)),
                Widgets::kpi('refunded_amount', 'Montant remboursé', 'Amount refunded', (int) ($refunded->amount ?? 0), Widgets::MONEY),
            ],
            [Widgets::chart('by_status', 'Retours par statut', 'Returns by status', 'donut', $points)],
            [Widgets::table(
                'oldest_open',
                'Retours ouverts les plus anciens',
                'Oldest open returns',
                [['N°', 'No.'], ['Commande', 'Order'], ['Statut', 'Status'], ['Montant', 'Amount'], ['Date', 'Date']],
                $rows,
            )],
        );
    }
}
