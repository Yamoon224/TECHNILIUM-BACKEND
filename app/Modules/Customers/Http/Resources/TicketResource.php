<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Resources;

use App\Modules\Customers\Infrastructure\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A ticket as its customer sees it: no internal notes, no assignee, no SLA -
 * staff details live in StaffTicketResource.
 */
class TicketResource extends JsonResource
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
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
            'messages' => $this->whenLoaded('messages', fn () => $ticket->messages
                ->reject(fn ($message) => $message->internal)
                ->map(fn ($message) => [
                    'id' => $message->id,
                    'from' => $message->author_type === 'staff' ? 'support' : 'customer',
                    'body' => $message->body,
                    'created_at' => $message->created_at?->toIso8601String(),
                ])->values()->all()),
        ];
    }
}
