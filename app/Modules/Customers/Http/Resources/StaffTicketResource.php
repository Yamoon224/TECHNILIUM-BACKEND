<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Resources;

use App\Modules\Customers\Infrastructure\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Ticket $ticket */
        $ticket = $this->resource;

        return [
            'id' => $ticket->id,
            'number' => $ticket->number(),
            'subject' => $ticket->subject,
            'category' => $ticket->category->value,
            'priority' => $ticket->priority->value,
            'status' => $ticket->status->value,
            'order_number' => $ticket->order_number,
            'customer_id' => $ticket->customer_id,
            'assigned_to' => $ticket->assigned_to,
            'overdue' => $ticket->isOverdue(),
            'first_responded_at' => $ticket->first_responded_at?->toIso8601String(),
            'resolved_at' => $ticket->resolved_at?->toIso8601String(),
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
            'messages' => $this->whenLoaded('messages', fn () => $ticket->messages->map(fn ($message) => [
                'id' => $message->id,
                'from' => $message->author_type,
                'author_id' => $message->author_id,
                'body' => $message->body,
                'internal' => $message->internal,
                'created_at' => $message->created_at?->toIso8601String(),
            ])->all()),
        ];
    }
}
