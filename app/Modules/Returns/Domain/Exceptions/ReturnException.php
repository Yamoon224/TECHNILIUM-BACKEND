<?php

declare(strict_types=1);

namespace App\Modules\Returns\Domain\Exceptions;

use App\Modules\Returns\Domain\ReturnStatus;
use DomainException;

final class ReturnException extends DomainException
{
    public static function orderNotFound(string $orderNumber): self
    {
        return new self("Commande {$orderNumber} introuvable.");
    }

    public static function orderNotDelivered(string $orderNumber): self
    {
        return new self("La commande {$orderNumber} n'est pas livrée : aucun retour possible.");
    }

    public static function windowExpired(string $orderNumber, int $days): self
    {
        return new self("Le délai de retour de {$days} jours est dépassé pour la commande {$orderNumber}.");
    }

    public static function amountExceedsRefundable(int $refundable): self
    {
        return new self("Le montant dépasse ce qui reste remboursable sur la commande ({$refundable}).");
    }

    public static function invalidTransition(ReturnStatus $from, ReturnStatus $to): self
    {
        return new self("Transition de retour interdite : {$from->value} → {$to->value}.");
    }
}
