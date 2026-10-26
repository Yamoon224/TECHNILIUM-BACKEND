<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Domain\Exceptions;

use App\Modules\Shipping\Domain\ShipmentStatus;
use DomainException;

final class InvalidShipmentTransitionException extends DomainException
{
    public static function from(ShipmentStatus $current, ShipmentStatus $target): self
    {
        return new self(sprintf(
            'Cannot transition shipment from "%s" to "%s".',
            $current->value,
            $target->value,
        ));
    }
}
