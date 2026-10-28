<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Contracts;

use App\Modules\Notifications\Domain\Exceptions\DeliveryFailedException;

/**
 * Port for one way of reaching a customer (SMS gateway, WhatsApp...). Any
 * implementation must be interchangeable (cahier §10.1, L): same contract,
 * and a failure is always signalled by DeliveryFailedException.
 */
interface NotificationChannelInterface
{
    /**
     * @throws DeliveryFailedException
     */
    public function send(string $recipient, string $body, string $locale = 'fr'): void;
}
