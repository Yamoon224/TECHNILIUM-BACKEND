<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

use RuntimeException;

final class InternalSaleException extends RuntimeException
{
    public static function vendorOffer(int $offerId): self
    {
        return new self("L'offre {$offerId} est vendue par un vendeur tiers : une vente interne ne porte que sur le stock Technilium.");
    }

    public static function nothingToClose(): self
    {
        return new self('Rien à clôturer : aucune vente ni annulation depuis la dernière clôture.');
    }

    public static function notFound(string $orderNumber): self
    {
        return new self("Vente interne {$orderNumber} introuvable.");
    }

    public static function alreadyCancelled(string $orderNumber): self
    {
        return new self("La vente {$orderNumber} est déjà annulée.");
    }

    public static function insufficientCash(int $due, int $tendered): self
    {
        return new self("Les espèces remises ({$tendered}) sont inférieures au montant dû ({$due}).");
    }
}
