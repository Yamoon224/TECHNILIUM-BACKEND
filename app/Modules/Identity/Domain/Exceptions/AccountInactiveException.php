<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exceptions;

use DomainException;

final class AccountInactiveException extends DomainException
{
    public static function create(): self
    {
        return new self('Ce compte a été désactivé.');
    }
}
