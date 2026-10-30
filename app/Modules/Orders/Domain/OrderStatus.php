<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain;

/**
 * Simplified first pass at the order lifecycle. The cahier des charges
 * (§3.2) describes an 18-state machine with sub-orders per vendor, payment
 * confirmation, delivery attempts, returns and disputes - this is the
 * paiement-à-la-livraison-only subset needed for a first working checkout.
 * Extend (don't silently widen) when Payments/Shipping/Returns land.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
