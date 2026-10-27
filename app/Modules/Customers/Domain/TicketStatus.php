<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain;

/**
 * Support ticket lifecycle (cahier §6.12). A staff reply parks the ticket on
 * the customer, a customer reply puts it back in the team's queue, and a
 * closed ticket is final.
 */
enum TicketStatus: string
{
    case Open = 'open';
    case PendingCustomer = 'pending_customer';
    case Resolved = 'resolved';
    case Closed = 'closed';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::PendingCustomer, self::Resolved],
            self::PendingCustomer => [self::Open, self::Resolved],
            // Resolved can be reopened (customer not satisfied) or closed for good.
            self::Resolved => [self::Open, self::Closed],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** Still being worked on, i.e. counts toward the team's backlog. */
    public function isActive(): bool
    {
        return $this === self::Open || $this === self::PendingCustomer;
    }
}
