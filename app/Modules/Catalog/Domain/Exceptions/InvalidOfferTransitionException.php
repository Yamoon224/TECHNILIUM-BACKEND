<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Exceptions;

use App\Modules\Catalog\Domain\OfferStatus;
use DomainException;

final class InvalidOfferTransitionException extends DomainException
{
    public static function from(OfferStatus $current, OfferStatus $target): self
    {
        return new self(sprintf(
            'Cannot transition offer from "%s" to "%s".',
            $current->value,
            $target->value,
        ));
    }
}
