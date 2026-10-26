<?php

declare(strict_types=1);

namespace App\Modules\Vendors\Domain\Exceptions;

use App\Modules\Vendors\Domain\VendorStatus;
use DomainException;

final class InvalidVendorTransitionException extends DomainException
{
    public static function from(VendorStatus $current, VendorStatus $target): self
    {
        return new self(sprintf(
            'Cannot transition vendor from "%s" to "%s".',
            $current->value,
            $target->value,
        ));
    }
}
