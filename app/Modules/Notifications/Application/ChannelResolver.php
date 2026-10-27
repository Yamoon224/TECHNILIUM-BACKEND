<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application;

use App\Modules\Notifications\Application\Channels\LogChannel;
use App\Modules\Notifications\Application\Contracts\NotificationChannelInterface;
use App\Modules\Notifications\Domain\Exceptions\DeliveryFailedException;

/**
 * Maps a channel (sms, whatsapp) to the driver configured for it. Adding a
 * provider = one class implementing NotificationChannelInterface plus one
 * line in DRIVERS; no caller changes (cahier §10.1, O).
 */
final class ChannelResolver
{
    /** @var array<string, class-string<NotificationChannelInterface>> */
    private const DRIVERS = [
        'log' => LogChannel::class,
    ];

    public function resolve(string $channel): NotificationChannelInterface
    {
        $driver = (string) config("notifications.drivers.{$channel}", 'log');
        $class = self::DRIVERS[$driver] ?? throw DeliveryFailedException::unknownDriver($channel, $driver);

        return app($class);
    }
}
