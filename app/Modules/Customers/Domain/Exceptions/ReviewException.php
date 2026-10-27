<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain\Exceptions;

use DomainException;

final class ReviewException extends DomainException
{
    public static function productUnavailable(): self
    {
        return new self("Ce produit n'est pas disponible pour un avis.");
    }

    public static function notPurchased(): self
    {
        return new self('Seuls les clients ayant reçu ce produit peuvent donner un avis.');
    }

    public static function alreadyReviewed(): self
    {
        return new self('Vous avez déjà donné un avis sur ce produit.');
    }

    public static function alreadyInState(string $status): self
    {
        return new self("L'avis est déjà « {$status} ».");
    }
}
