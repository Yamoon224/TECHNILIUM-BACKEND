<?php

declare(strict_types=1);

namespace App\Modules\Payouts\Domain\Exceptions;

use DomainException;

final class PayoutException extends DomainException
{
    public static function nothingPayable(int $vendorId): self
    {
        return new self("Aucune vente exigible pour le vendeur {$vendorId}.");
    }

    public static function alreadyPaid(int $payoutId): self
    {
        return new self("Le reversement {$payoutId} n'est pas en attente.");
    }

    public static function immutable(): self
    {
        return new self('Les montants d\'un reversement ne se modifient pas.');
    }
}
