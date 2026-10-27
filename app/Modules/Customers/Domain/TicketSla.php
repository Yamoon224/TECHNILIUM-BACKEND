<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * First-response target (cahier §6.12 "délais de réponse cibles"): how long
 * a customer waits, by priority, before a ticket counts as late. Pure, so the
 * rule is unit-tested and changing a delay touches config only.
 */
final class TicketSla
{
    /** @param array<string, int> $hoursByPriority */
    public function __construct(private readonly array $hoursByPriority) {}

    public function dueAt(TicketPriority $priority, DateTimeInterface $createdAt): DateTimeImmutable
    {
        $hours = $this->hoursByPriority[$priority->value] ?? 24;

        return DateTimeImmutable::createFromInterface($createdAt)->modify("+{$hours} hours");
    }

    /**
     * Late = still waiting for the team's first reply past the target. Once
     * staff answered (or the ticket left the queue) it can no longer be late.
     */
    public function isOverdue(
        TicketStatus $status,
        TicketPriority $priority,
        DateTimeInterface $createdAt,
        ?DateTimeInterface $firstRespondedAt,
        DateTimeInterface $now,
    ): bool {
        if ($firstRespondedAt !== null || $status !== TicketStatus::Open) {
            return false;
        }

        return $now > $this->dueAt($priority, $createdAt);
    }
}
