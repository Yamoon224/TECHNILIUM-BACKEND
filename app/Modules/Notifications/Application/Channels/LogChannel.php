<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Channels;

use App\Modules\Notifications\Application\Contracts\NotificationChannelInterface;
use Illuminate\Support\Facades\Log;

/**
 * Development / pre-provider driver: nothing leaves the server. The message
 * is written to the application log (recipient masked, as phone numbers are
 * personal data) so a flow can be followed end to end without a gateway.
 */
final class LogChannel implements NotificationChannelInterface
{
    public function send(string $recipient, string $body, string $locale = 'fr'): void
    {
        Log::info('Notification simulée', [
            'recipient' => self::mask($recipient),
            'body' => $body,
        ]);
    }

    /** "0700000001" -> "07******01". */
    public static function mask(string $recipient): string
    {
        $length = strlen($recipient);

        return $length <= 4
            ? str_repeat('*', $length)
            : substr($recipient, 0, 2).str_repeat('*', $length - 4).substr($recipient, -2);
    }
}
