<?php

declare(strict_types=1);

namespace App\Modules\Customers\Application\Actions;

use App\Modules\Customers\Domain\Exceptions\TicketException;
use App\Modules\Customers\Domain\TicketStatus;
use App\Modules\Customers\Infrastructure\Models\Ticket;
use App\Modules\Customers\Infrastructure\Models\TicketMessage;
use Illuminate\Support\Facades\DB;

/**
 * Adds a message to a ticket and moves its status the way a conversation
 * does: a team reply waits on the customer, a customer reply (even on a
 * resolved ticket) puts it back in the team's queue, an internal note
 * changes nothing. A closed ticket accepts nothing.
 */
final class ReplyToTicketAction
{
    public function customerReply(Ticket $ticket, int $customerId, string $body): TicketMessage
    {
        return $this->add($ticket, 'customer', $customerId, $body, false);
    }

    public function staffReply(Ticket $ticket, int $staffId, string $body, bool $internal = false): TicketMessage
    {
        return $this->add($ticket, 'staff', $staffId, $body, $internal);
    }

    private function add(Ticket $ticket, string $authorType, int $authorId, string $body, bool $internal): TicketMessage
    {
        return DB::transaction(function () use ($ticket, $authorType, $authorId, $body, $internal) {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($ticket->status === TicketStatus::Closed) {
                throw TicketException::closed($ticket->number());
            }

            $message = $ticket->messages()->create([
                'author_type' => $authorType,
                'author_id' => $authorId,
                'body' => $body,
                'internal' => $internal,
            ]);

            if ($internal) {
                return $message; // a private note is invisible to the customer: no status effect
            }

            if ($authorType === 'staff') {
                // The first answer to the customer stops the SLA clock, once.
                $ticket->first_responded_at ??= now();

                if ($ticket->status === TicketStatus::Open) {
                    $ticket->status = TicketStatus::PendingCustomer;
                }
            } elseif ($ticket->status !== TicketStatus::Open) {
                $ticket->status = TicketStatus::Open;
                $ticket->resolved_at = null;
            }

            $ticket->save();

            return $message;
        });
    }
}
