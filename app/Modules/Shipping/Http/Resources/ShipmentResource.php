<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Http\Resources;

use App\Modules\Shipping\Application\Actions\ConfirmDeliveryAction;
use App\Modules\Shipping\Infrastructure\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Shipment $shipment */
        $shipment = $this->resource;

        return [
            'id' => $shipment->id,
            'order_number' => $shipment->order_number,
            'assigned_to' => $shipment->assigned_to,
            'customer_name' => $shipment->customer_name,
            'customer_phone' => $shipment->customer_phone,
            'delivery_address' => $shipment->delivery_address,
            'delivery_city' => $shipment->delivery_city,
            'status' => $shipment->status->value,
            'attempts' => $shipment->attempts,
            'code_attempts' => $shipment->code_attempts,
            'code_locked' => $shipment->code_attempts >= ConfirmDeliveryAction::MAX_CODE_ATTEMPTS,
            'notes' => $shipment->notes,
            'delivered_at' => $shipment->delivered_at?->toIso8601String(),
            'created_at' => $shipment->created_at?->toIso8601String(),
        ];
    }
}
