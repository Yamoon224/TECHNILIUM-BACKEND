<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Exceptions;

use RuntimeException;

/** A channel could not deliver (provider down, bad number, unknown driver...). */
final class DeliveryFailedException extends RuntimeException
{
    public static function unknownDriver(string $channel, string $driver): self
    {
        return new self("Aucun pilote « {$driver} » pour le canal {$channel}.");
    }
}
