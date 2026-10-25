<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain\Exceptions;

use DomainException;

final class OfferUnavailableException extends DomainException
{
    public static function notFound(int $offerId): self
    {
        return new self("L'offre #{$offerId} n'est plus disponible.");
    }

    public static function insufficientStock(int $offerId): self
    {
        return new self("Stock insuffisant pour l'offre #{$offerId}.");
    }
}
