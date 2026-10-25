<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Exceptions;

use DomainException;

final class InsufficientStockException extends DomainException
{
    public static function forOffer(int $offerId, int $requested, int $available): self
    {
        return new self(sprintf(
            'Offer #%d: requested %d but only %d in stock.',
            $offerId,
            $requested,
            $available,
        ));
    }
}
