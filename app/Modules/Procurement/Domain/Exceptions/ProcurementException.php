<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Domain\Exceptions;

use App\Modules\Procurement\Domain\PurchaseOrderStatus;
use DomainException;

final class ProcurementException extends DomainException
{
    public static function offerNotPurchasable(int $offerId): self
    {
        return new self("L'offre {$offerId} est introuvable ou n'est pas du stock propre de Technilium (achat impossible).");
    }

    public static function duplicateOffer(int $offerId): self
    {
        return new self("L'offre {$offerId} figure plusieurs fois sur le bon de commande.");
    }

    public static function invalidTransition(PurchaseOrderStatus $from, PurchaseOrderStatus $to): self
    {
        return new self("Transition de bon de commande interdite : {$from->value} → {$to->value}.");
    }

    public static function notReceivable(string $number, PurchaseOrderStatus $status): self
    {
        return new self("Le bon {$number} ne peut pas recevoir de marchandises (statut {$status->value}).");
    }

    public static function lineNotOnOrder(int $lineId): self
    {
        return new self("La ligne {$lineId} n'appartient pas à ce bon de commande.");
    }

    public static function exceedsOutstanding(int $lineId, int $outstanding): self
    {
        return new self("La ligne {$lineId} dépasse ce qui reste à recevoir ({$outstanding}).");
    }

    public static function nothingReceived(): self
    {
        return new self('Une réception doit contenir au moins une unité acceptée ou refusée.');
    }

    public static function alreadyReceived(string $number): self
    {
        return new self("Le bon {$number} a déjà reçu des marchandises : il ne peut plus être annulé.");
    }

    public static function amountExceedsOutstanding(int $outstanding): self
    {
        return new self("Le montant dépasse le reste à payer sur la facture ({$outstanding}).");
    }

    public static function invoiceSettled(string $number): self
    {
        return new self("La facture {$number} est déjà soldée.");
    }
}
