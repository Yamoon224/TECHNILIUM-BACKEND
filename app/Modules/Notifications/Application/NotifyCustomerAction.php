<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application;

use App\Modules\Notifications\Application\Jobs\SendNotificationJob;
use App\Modules\Notifications\Domain\NotificationStatus;
use App\Modules\Notifications\Infrastructure\Models\Notification;

/**
 * Records one message per enabled channel and queues its delivery. Idempotent
 * per (event, reference, channel): a replayed domain event records nothing new.
 */
final class NotifyCustomerAction
{
    public function execute(string $event, string $reference, string $phone, string $body, ?string $email = null): void
    {
        $phone = trim($phone);
        $email = trim((string) $email);

        /** @var list<string> $channels */
        $channels = array_values((array) config('notifications.channels'));

        foreach ($channels as $channel) {
            // E-mail goes to the optional address, every other channel to the phone.
            $recipient = $channel === 'email' ? $email : $phone;

            if ($recipient === '') {
                continue;
            }

            $notification = Notification::query()->firstOrCreate(
                ['dedupe_key' => "{$event}:{$reference}:{$channel}"],
                [
                    'event' => $event,
                    'channel' => $channel,
                    'recipient' => $recipient,
                    'body' => $body,
                    'reference' => $reference,
                    'status' => NotificationStatus::Pending,
                ],
            );

            if ($notification->wasRecentlyCreated) {
                SendNotificationJob::dispatch($notification->id);
            }
        }
    }
}
