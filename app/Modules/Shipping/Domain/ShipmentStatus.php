<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Domain;

/**
 * Delivery tracking (cahier §8 "logistique"). Independent of Orders'
 * own status — a shipment can be dispatched/retried/returned without
 * forcing Orders to model the same granularity, and nudges Orders'
 * status forward on a best-effort basis (see OrderLookupGatewayInterface).
 */
enum ShipmentStatus: string
{
    case Pending = 'pending';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case FailedAttempt = 'failed_attempt';
    case Returned = 'returned';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::InTransit],
            self::InTransit => [self::Delivered, self::FailedAttempt],
            self::FailedAttempt => [self::InTransit, self::Returned],
            self::Delivered, self::Returned => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
