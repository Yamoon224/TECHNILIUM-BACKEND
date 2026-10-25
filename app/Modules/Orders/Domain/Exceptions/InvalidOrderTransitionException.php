<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain\Exceptions;

use App\Modules\Orders\Domain\OrderStatus;
use DomainException;

final class InvalidOrderTransitionException extends DomainException
{
    public static function from(OrderStatus $current, OrderStatus $target): self
    {
        return new self(sprintf(
            'Cannot transition order from "%s" to "%s".',
            $current->value,
            $target->value,
        ));
    }
}
