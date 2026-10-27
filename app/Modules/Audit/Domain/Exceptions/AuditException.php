<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain\Exceptions;

use DomainException;

final class AuditException extends DomainException
{
    public static function immutable(): self
    {
        return new self("Le journal d'audit est en écriture seule : une entrée ne se modifie ni ne se supprime.");
    }
}
