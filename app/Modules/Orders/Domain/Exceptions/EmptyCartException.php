<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain\Exceptions;

use DomainException;

final class EmptyCartException extends DomainException
{
    public static function create(): self
    {
        return new self('Le panier est vide.');
    }
}
