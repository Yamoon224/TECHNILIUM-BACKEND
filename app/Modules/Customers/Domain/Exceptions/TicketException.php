<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain\Exceptions;

use App\Modules\Customers\Domain\TicketStatus;
use DomainException;

final class TicketException extends DomainException
{
    public static function orderNotYours(string $orderNumber): self
    {
        return new self("La commande {$orderNumber} est introuvable sur votre compte.");
    }

    public static function closed(string $number): self
    {
        return new self("Le ticket {$number} est clos : ouvrez-en un nouveau.");
    }

    public static function invalidTransition(TicketStatus $from, TicketStatus $to): self
    {
        return new self("Transition de ticket interdite : {$from->value} → {$to->value}.");
    }
}
