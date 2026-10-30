<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application\Dashboard;

use App\Modules\Audit\Infrastructure\Models\AuditLog;
use App\Modules\Shared\Dashboard\DashboardSection;
use App\Modules\Shared\Dashboard\Widgets;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Dashboard block of the audit log: activity of the period and the latest
 * actions. Only who/what/when/status is exposed, never payloads or tokens.
 */
final class AuditSection implements DashboardSection
{
    private const MAX_POINTS = 31;

    public function key(): string
    {
        return 'audit';
    }

    public function ability(): string
    {
        return 'view-audit';
    }

    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        $stats = AuditLog::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->selectRaw('count(*) as actions, count(distinct user_id) as actors, coalesce(sum(case when status >= 400 then 1 else 0 end), 0) as failures')
            ->toBase()
            ->first();

        $failures = (int) ($stats->failures ?? 0);

        $latest = AuditLog::query()
            ->select(['id', 'user_name', 'method', 'path', 'status', 'occurred_at'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (AuditLog $entry) => [
                $entry->occurred_at->format('d/m/Y H:i'),
                $entry->user_name ?: '-',
                $entry->method.' '.strtok($entry->path, '?'),
                $entry->status,
            ])
            ->all();

        return Widgets::section(
            "Journal d'audit",
            'Audit log',
            '/audit',
            [
                Widgets::kpi('actions', 'Actions journalisées', 'Logged actions', (int) ($stats->actions ?? 0)),
                Widgets::kpi('actors', 'Acteurs distincts', 'Distinct actors', (int) ($stats->actors ?? 0)),
                Widgets::kpi('failures', 'Échecs ou refus', 'Failures or denials', $failures, Widgets::NUMBER, $failures > 0 ? Widgets::WARN : null),
            ],
            [
                Widgets::chart('actions_by_day', 'Actions par jour', 'Actions per day', 'bar', $this->byDay($from, $to)),
            ],
            [
                Widgets::table('latest_actions', 'Dernières actions', 'Latest actions', [
                    ['Date', 'Date'], ['Acteur', 'Actor'], ['Action', 'Action'], ['Statut', 'Status'],
                ], $latest),
            ],
        );
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private function byDay(CarbonInterface $from, CarbonInterface $to): array
    {
        $start = CarbonImmutable::instance($from)->startOfDay();
        $days = max(1, (int) $start->diffInDays(CarbonImmutable::instance($to)->startOfDay()) + 1);
        $step = (int) ceil($days / self::MAX_POINTS);

        $perDay = AuditLog::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->selectRaw('DATE(occurred_at) as day, count(*) as total')
            ->groupBy('day')
            ->toBase()
            ->pluck('total', 'day');

        $points = [];

        for ($offset = 0; $offset < $days; $offset += $step) {
            $total = 0;

            for ($i = $offset; $i < min($offset + $step, $days); $i++) {
                $total += (int) ($perDay[$start->addDays($i)->toDateString()] ?? 0);
            }

            $points[] = ['label' => $start->addDays($offset)->format('d/m'), 'value' => $total];
        }

        return $points;
    }
}
