<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Exceptions;

use DomainException;

final class InvalidRefundException extends DomainException
{
    public static function notRefundable(int $paymentId): self
    {
        return new self("Le paiement {$paymentId} n'est pas remboursable (non encaissé ou déjà intégralement remboursé).");
    }

    public static function amountExceedsRefundable(int $refundable): self
    {
        return new self("Le montant dépasse ce qui reste remboursable sur le paiement ({$refundable}).");
    }
}
