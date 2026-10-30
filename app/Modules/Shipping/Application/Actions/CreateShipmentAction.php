<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Application\Actions;

use App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface;
use App\Modules\Shipping\Domain\ShipmentStatus;
use App\Modules\Shipping\Infrastructure\Models\Shipment;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Snapshots the order's delivery details onto a new shipment (cahier's
 * established pattern - see Orders' order_items) via Orders' public
 * contract, never its Eloquent models (cahier §9.1).
 */
final class CreateShipmentAction
{
    public function __construct(
        private readonly OrderLookupGatewayInterface $orders,
    ) {}

    public function execute(string $orderNumber, ?int $assignedTo): Shipment
    {
        $order = $this->orders->find($orderNumber);

        if ($order === null) {
            throw new ModelNotFoundException("Order \"{$orderNumber}\" not found.");
        }

        return Shipment::query()->create([
            'order_number' => $order->orderNumber,
            'assigned_to' => $assignedTo,
            'customer_name' => $order->customerName,
            'customer_phone' => $order->customerPhone,
            'delivery_address' => $order->deliveryAddress,
            'delivery_city' => $order->deliveryCity,
            'status' => ShipmentStatus::Pending->value,
            'attempts' => 0,
        ]);
    }
}
