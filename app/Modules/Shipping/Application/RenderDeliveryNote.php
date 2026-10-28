<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Application;

use App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface;
use App\Modules\Shipping\Domain\DeliveryNoteLabels;
use App\Modules\Shipping\Infrastructure\Models\Shipment;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * The paper a courier carries: who to deliver to and what to hand over, with a
 * signature area. It carries no price and never the delivery confirmation code
 * (that is the customer's secret, given to the courier at handover).
 */
class RenderDeliveryNote
{
    public function __construct(private readonly OrderLookupGatewayInterface $orders) {}

    public function html(Shipment $shipment, string $locale = 'fr'): string
    {
        $labels = DeliveryNoteLabels::for(DeliveryNoteLabels::normalize($locale));
        $order = $this->orders->find($shipment->order_number);

        return view('shipping::delivery-note', [
            'shipment' => $shipment,
            't' => $labels,
            'company' => (array) config('invoicing.company'),
            // Description and quantity only: no price on a courier's paper.
            'lines' => $order === null ? [] : array_map(fn ($line) => ['description' => $line->description, 'quantity' => $line->quantity], $order->lines),
        ])->render();
    }

    public function pdf(Shipment $shipment, string $locale = 'fr'): string
    {
        return Pdf::loadHTML($this->html($shipment, $locale))
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', false)
            ->output();
    }

    public function filename(Shipment $shipment): string
    {
        return 'BL-'.sprintf('%06d', $shipment->id).'.pdf';
    }
}
