<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Channels;

use App\Modules\Notifications\Application\Contracts\NotificationChannelInterface;
use App\Modules\Notifications\Domain\Exceptions\DeliveryFailedException;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Sends the message as a plain-text e-mail through the application mailer (MAIL_* settings). */
final class MailChannel implements NotificationChannelInterface
{
    public function send(string $recipient, string $body): void
    {
        try {
            Mail::raw($body, function ($message) use ($recipient): void {
                $message->to($recipient)->subject(config('app.name').' — votre commande');
            });
        } catch (Throwable $exception) {
            throw DeliveryFailedException::providerError('email', $exception->getMessage());
        }
    }
}
