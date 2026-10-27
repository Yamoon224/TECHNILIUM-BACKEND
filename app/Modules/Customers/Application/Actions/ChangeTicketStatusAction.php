<?php

declare(strict_types=1);

namespace App\Modules\Customers\Application\Actions;

use App\Modules\Customers\Domain\Exceptions\TicketException;
use App\Modules\Customers\Domain\TicketStatus;
use App\Modules\Customers\Infrastructure\Models\Ticket;
use Illuminate\Support\Facades\DB;

/** Staff moves a ticket along its lifecycle; only declared transitions are allowed. */
final class ChangeTicketStatusAction
{
    public function execute(Ticket $ticket, TicketStatus $target): Ticket
    {
        return DB::transaction(function () use ($ticket, $target) {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            if (! $ticket->status->canTransitionTo($target)) {
                throw TicketException::invalidTransition($ticket->status, $target);
            }

            $ticket->status = $target;
            $ticket->resolved_at = $target === TicketStatus::Resolved ? now() : ($target === TicketStatus::Open ? null : $ticket->resolved_at);
            $ticket->save();

            return $ticket;
        });
    }
}
