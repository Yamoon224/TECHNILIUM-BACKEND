<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Application\Actions;

use App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface;
use App\Modules\Shipping\Domain\Exceptions\InvalidShipmentTransitionException;
use App\Modules\Shipping\Domain\ShipmentStatus;
use App\Modules\Shipping\Infrastructure\Models\Shipment;

/**
 * Single entry point for every shipment status change - mirrors Catalog's
 * TransitionOfferStatusAction so a status is never written outside of an
 * authorized transition.
 */
final class TransitionShipmentStatusAction
{
    public function __construct(
        private readonly OrderLookupGatewayInterface $orders,
    ) {}

    public function execute(Shipment $shipment, ShipmentStatus $target, ?string $notes = null): Shipment
    {
        $current = $shipment->status;

        if (! $current->canTransitionTo($target)) {
            throw InvalidShipmentTransitionException::from($current, $target);
        }

        $shipment->status = $target;

        if ($target === ShipmentStatus::FailedAttempt) {
            $shipment->attempts += 1;
        }

        if ($target === ShipmentStatus::Delivered) {
            $shipment->delivered_at = now();
        }

        if ($notes !== null) {
            $shipment->notes = $notes;
        }

        $shipment->save();

        // Best-effort sync back to Orders - see OrderLookupGatewayInterface.
        if ($target === ShipmentStatus::InTransit) {
            $this->orders->markShipped($shipment->order_number);
        } elseif ($target === ShipmentStatus::Delivered) {
            $this->orders->markDelivered($shipment->order_number);
        }

        return $shipment->refresh();
    }
}
