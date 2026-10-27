<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Domain\Exceptions;

use DomainException;

final class DeliveryCodeException extends DomainException
{
    public static function wrong(int $remaining): self
    {
        return new self("Code de livraison incorrect. Tentatives restantes : {$remaining}.");
    }

    public static function locked(): self
    {
        return new self('Trop de codes incorrects : la livraison est verrouillée, contactez un responsable.');
    }
}
