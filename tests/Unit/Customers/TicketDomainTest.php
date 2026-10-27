<?php

declare(strict_types=1);

use App\Modules\Customers\Domain\TicketPriority;
use App\Modules\Customers\Domain\TicketSla;
use App\Modules\Customers\Domain\TicketStatus;

it('allows exactly the ticket transitions', function (TicketStatus $from, TicketStatus $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    [TicketStatus::Open, TicketStatus::PendingCustomer, true],
    [TicketStatus::Open, TicketStatus::Resolved, true],
    [TicketStatus::Open, TicketStatus::Closed, false],
    [TicketStatus::PendingCustomer, TicketStatus::Open, true],
    [TicketStatus::PendingCustomer, TicketStatus::Resolved, true],
    [TicketStatus::Resolved, TicketStatus::Open, true],
    [TicketStatus::Resolved, TicketStatus::Closed, true],
    [TicketStatus::Resolved, TicketStatus::PendingCustomer, false],
    [TicketStatus::Closed, TicketStatus::Open, false],
    [TicketStatus::Closed, TicketStatus::Resolved, false],
]);

it('counts only open and pending tickets as active', function () {
    expect(TicketStatus::Open->isActive())->toBeTrue()
        ->and(TicketStatus::PendingCustomer->isActive())->toBeTrue()
        ->and(TicketStatus::Resolved->isActive())->toBeFalse()
        ->and(TicketStatus::Closed->isActive())->toBeFalse();
});

function sla(): TicketSla
{
    return new TicketSla(['high' => 2, 'normal' => 8, 'low' => 24]);
}

it('computes the first-response deadline from the priority', function () {
    $created = new DateTimeImmutable('2026-10-27 09:00:00');

    expect(sla()->dueAt(TicketPriority::High, $created)->format('H:i'))->toBe('11:00')
        ->and(sla()->dueAt(TicketPriority::Normal, $created)->format('H:i'))->toBe('17:00')
        ->and(sla()->dueAt(TicketPriority::Low, $created)->format('Y-m-d H:i'))->toBe('2026-10-28 09:00');
});

it('is overdue only while open, unanswered and past the deadline', function () {
    $created = new DateTimeImmutable('2026-10-27 09:00:00');
    $late = new DateTimeImmutable('2026-10-27 18:00:00');
    $early = new DateTimeImmutable('2026-10-27 12:00:00');
    $open = TicketStatus::Open;
    $normal = TicketPriority::Normal;

    expect(sla()->isOverdue($open, $normal, $created, null, $late))->toBeTrue()
        ->and(sla()->isOverdue($open, $normal, $created, null, $early))->toBeFalse()
        // Already answered: never late, however long ago.
        ->and(sla()->isOverdue($open, $normal, $created, $early, $late))->toBeFalse()
        // Out of the queue: not late.
        ->and(sla()->isOverdue(TicketStatus::PendingCustomer, $normal, $created, null, $late))->toBeFalse()
        ->and(sla()->isOverdue(TicketStatus::Resolved, $normal, $created, null, $late))->toBeFalse();
});

it('falls back to 24 hours for an unconfigured priority', function () {
    $created = new DateTimeImmutable('2026-10-27 09:00:00');

    expect((new TicketSla([]))->dueAt(TicketPriority::High, $created)->format('Y-m-d H:i'))->toBe('2026-10-28 09:00');
});
