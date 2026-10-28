<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Jobs;

use App\Modules\Notifications\Application\ChannelResolver;
use App\Modules\Notifications\Domain\NotificationStatus;
use App\Modules\Notifications\Infrastructure\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Delivers one recorded notification. Runs after the business transaction
 * committed (so a rolled-back order sends nothing), and a failure is stored
 * on the row rather than thrown: it must never surface into the order flow.
 * Failed messages are retried from the back office.
 */
final class SendNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(public readonly int $notificationId)
    {
        // Sent only once the surrounding business transaction has committed.
        $this->afterCommit();
    }

    public function handle(ChannelResolver $channels): void
    {
        $notification = Notification::query()->find($this->notificationId);

        // Already delivered (or gone): a replayed job must not send twice.
        if ($notification === null || $notification->status === NotificationStatus::Sent) {
            return;
        }

        $notification->attempts += 1;

        try {
            $channels->resolve($notification->channel)->send($notification->recipient, $notification->body, (string) ($notification->locale ?? 'fr'));

            $notification->status = NotificationStatus::Sent;
            $notification->sent_at = now();
            $notification->error = null;
        } catch (Throwable $exception) {
            $notification->status = NotificationStatus::Failed;
            $notification->error = mb_substr($exception->getMessage(), 0, 500);
        }

        $notification->save();
    }
}
