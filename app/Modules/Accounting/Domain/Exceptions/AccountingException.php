<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use DomainException;

final class AccountingException extends DomainException
{
    public static function unbalanced(int $debit, int $credit): self
    {
        return new self("Écriture déséquilibrée : débit {$debit} ≠ crédit {$credit}.");
    }

    public static function immutable(): self
    {
        return new self('Le grand livre est en écriture seule : une écriture se contre-passe, elle ne se modifie pas.');
    }
}
