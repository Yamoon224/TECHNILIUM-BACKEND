<?php

declare(strict_types=1);

namespace App\Modules\Shared\Exceptions;

use App\Modules\Shared\ValueObjects\Currency;
use DomainException;

final class CurrencyMismatchException extends DomainException
{
    public static function between(Currency $a, Currency $b): self
    {
        return new self(sprintf('Cannot operate on different currencies: %s / %s', $a->value, $b->value));
    }
}
