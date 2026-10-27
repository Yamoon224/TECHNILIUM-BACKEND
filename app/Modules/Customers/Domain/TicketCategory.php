<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain;

enum TicketCategory: string
{
    case Order = 'order';
    case Payment = 'payment';
    case Delivery = 'delivery';
    case Product = 'product';
    case Warranty = 'warranty';
    case Other = 'other';
}
