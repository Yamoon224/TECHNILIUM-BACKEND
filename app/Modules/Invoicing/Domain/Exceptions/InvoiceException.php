<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Domain\Exceptions;

use DomainException;

final class InvoiceException extends DomainException
{
    public static function orderNotFound(string $orderNumber): self
    {
        return new self("Commande {$orderNumber} introuvable.");
    }

    public static function orderNotInvoiceable(string $orderNumber, string $status): self
    {
        return new self("La commande {$orderNumber} ne peut pas être facturée (statut {$status}).");
    }

    public static function alreadyInvoiced(string $orderNumber): self
    {
        return new self("La commande {$orderNumber} a déjà une facture.");
    }

    public static function notCreditable(string $number): self
    {
        return new self("Le document {$number} ne peut pas être annulé par un avoir (déjà crédité ou lui-même un avoir).");
    }

    public static function immutable(): self
    {
        return new self('Un document émis est immuable : toute correction passe par un avoir.');
    }
}
