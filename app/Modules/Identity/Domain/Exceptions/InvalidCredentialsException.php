<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exceptions;

use DomainException;

final class InvalidCredentialsException extends DomainException
{
    public static function create(): self
    {
        return new self('Les identifiants fournis sont incorrects.');
    }
}
