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

    public static function insufficientCash(int $due, int $tendered): self
    {
        return new self("Les espèces remises ({$tendered}) sont inférieures au montant dû ({$due}).");
    }
}
