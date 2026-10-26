<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain;

enum PaymentMethod: string
{
    case Cod = 'cod'; // paiement à la livraison
    case MobileMoney = 'mobile_money';
}
