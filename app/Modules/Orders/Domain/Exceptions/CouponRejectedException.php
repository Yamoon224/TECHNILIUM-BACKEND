<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain\Exceptions;

use DomainException;

final class CouponRejectedException extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
