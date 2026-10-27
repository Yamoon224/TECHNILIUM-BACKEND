<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Resources;

use App\Modules\Notifications\Infrastructure\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Notification $notification */
        $notification = $this->resource;

        return [
            'id' => $notification->id,
            'event' => $notification->event,
            'channel' => $notification->channel,
            'recipient' => $notification->recipient,
            'body' => $notification->body,
            'reference' => $notification->reference,
            'status' => $notification->status->value,
            'attempts' => $notification->attempts,
            'error' => $notification->error,
            'sent_at' => $notification->sent_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
