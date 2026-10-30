<?php

declare(strict_types=1);

namespace App\Modules\Customers\Application\Dashboard;

use App\Modules\Customers\Domain\TicketSla;
use App\Modules\Customers\Domain\TicketStatus;
use App\Modules\Customers\Infrastructure\Models\Ticket;
use App\Modules\Shared\Dashboard\DashboardSection;
use App\Modules\Shared\Dashboard\Widgets;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Dashboard block of the support feature: the team's backlog, tickets still
 * waiting for a first reply (and those past the SLA target), and the oldest
 * tickets to handle.
 */
final class SupportSection implements DashboardSection
{
    private const STATUS_LABELS = [
        'open' => ['Ouvert', 'Open'],
        'pending_customer' => ['En attente du client', 'Waiting for customer'],
        'resolved' => ['Résolu', 'Resolved'],
        'closed' => ['Clos', 'Closed'],
    ];

    public function __construct(private readonly TicketSla $sla) {}

    public function key(): string
    {
        return 'support';
    }

    public function ability(): string
    {
        return 'manage-support';
    }

    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        $now = CarbonImmutable::now();

        $byStatus = Ticket::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->toBase()
            ->pluck('total', 'status');

        $open = (int) ($byStatus[TicketStatus::Open->value] ?? 0) + (int) ($byStatus[TicketStatus::PendingCustomer->value] ?? 0);

        // Tickets in the team's queue that nobody answered yet: the only ones the SLA can flag.
        $unanswered = Ticket::query()
            ->where('status', TicketStatus::Open->value)
            ->whereNull('first_responded_at')
            ->get(['id', 'status', 'priority', 'created_at', 'first_responded_at']);

        $overdue = $unanswered->filter(fn (Ticket $ticket) => $this->sla->isOverdue(
            $ticket->status, $ticket->priority, $ticket->created_at ?? $now, $ticket->first_responded_at, $now,
        ))->count();

        $resolved = Ticket::query()->whereBetween('resolved_at', [$from, $to])->count();

        $donut = [];

        foreach (TicketStatus::cases() as $status) {
            $count = (int) ($byStatus[$status->value] ?? 0);

            if ($count > 0) {
                [$label, $labelEn] = self::STATUS_LABELS[$status->value];
                $donut[] = ['label' => $label, 'label_en' => $labelEn, 'value' => $count];
            }
        }

        $oldest = Ticket::query()
            ->whereIn('status', [TicketStatus::Open->value, TicketStatus::PendingCustomer->value])
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(8)
            ->get()
            ->map(fn (Ticket $ticket) => [
                $ticket->number().' · '.$ticket->subject,
                'Client #'.$ticket->customer_id,
                $this->age($ticket->created_at ?? $now, $now),
                self::STATUS_LABELS[$ticket->status->value][0],
            ])
            ->all();

        return Widgets::section(
            'Support',
            'Support',
            '/support',
            [
                Widgets::kpi('open', 'Tickets ouverts', 'Open tickets', $open),
                Widgets::kpi('unanswered', 'Sans première réponse', 'Awaiting first reply', $unanswered->count(), Widgets::NUMBER, $unanswered->isNotEmpty() ? Widgets::WARN : null),
                Widgets::kpi('sla_overdue', 'Délai de première réponse dépassé', 'First-reply target missed', $overdue, Widgets::NUMBER, $overdue > 0 ? Widgets::BAD : null),
                Widgets::kpi('resolved', 'Résolus sur la période', 'Resolved in the period', $resolved, Widgets::NUMBER, Widgets::GOOD),
            ],
            [
                Widgets::chart('by_status', 'Tickets par statut', 'Tickets by status', 'donut', $donut),
            ],
            [
                Widgets::table('oldest_open', 'Tickets ouverts les plus anciens', 'Oldest open tickets', [
                    ['Ticket', 'Ticket'], ['Client', 'Customer'], ['Ancienneté', 'Age'], ['Statut', 'Status'],
                ], $oldest),
            ],
        );
    }

    private function age(CarbonInterface $created, CarbonInterface $now): string
    {
        $hours = max(0, (int) $created->diffInHours($now, true));

        return $hours >= 48 ? intdiv($hours, 24).' j' : $hours.' h';
    }
}
