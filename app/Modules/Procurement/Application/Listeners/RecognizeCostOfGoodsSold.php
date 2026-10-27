<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Application\Listeners;

use App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface;
use App\Modules\Procurement\Application\Contracts\OfferCostInterface;
use App\Modules\Procurement\Infrastructure\Models\OrderCost;
use App\Modules\Procurement\Infrastructure\Models\OrderCostLine;
use App\Modules\Shared\Events\OrderStatusChanged;
use App\Modules\Shared\Events\StockConsumed;
use Throwable;

/**
 * When an order is delivered, its lines from Technilium's own stock leave
 * inventory at their weighted-average cost: that is the cost of goods sold
 * (cahier §6.9 "valorisation du stock et coûts des marchandises vendues").
 * Third-party vendors' lines are not our stock and cost us nothing here.
 *
 * The recognised cost is remembered per order, so a later customer return can
 * give back at most what was recognised (see ReverseCostOfGoodsSold).
 *
 * Side effect: it must never block a delivery, so failures are reported, not thrown.
 */
final class RecognizeCostOfGoodsSold
{
    public function __construct(
        private readonly OrderLookupGatewayInterface $orders,
        private readonly OfferCostInterface $costs,
    ) {}

    public function handle(OrderStatusChanged $event): void
    {
        if ($event->status !== 'delivered' || $event->orderId <= 0) {
            return;
        }

        try {
            $order = $this->orders->find($event->orderNumber);

            if ($order === null) {
                return;
            }

            $cost = 0;
            /** @var array<int, array{quantity: int, cost: int}> $byOffer */
            $byOffer = [];

            foreach ($order->lines as $line) {
                if ($line->vendorId === null && $line->offerId !== null) {
                    $lineCost = $this->costs->averageCost($line->offerId) * $line->quantity;
                    $cost += $lineCost;

                    $byOffer[$line->offerId] = [
                        'quantity' => ($byOffer[$line->offerId]['quantity'] ?? 0) + $line->quantity,
                        'cost' => ($byOffer[$line->offerId]['cost'] ?? 0) + $lineCost,
                    ];
                }
            }

            // No purchase history for these goods: there is no cost to recognise.
            if ($cost <= 0) {
                return;
            }

            $recorded = OrderCost::query()->firstOrCreate(
                ['order_id' => $event->orderId],
                ['order_number' => $event->orderNumber, 'cost_amount' => $cost],
            );

            // A replayed "delivered" finds the row already there and books nothing more.
            if ($recorded->wasRecentlyCreated) {
                foreach ($byOffer as $offerId => $row) {
                    OrderCostLine::query()->create([
                        'order_id' => $event->orderId,
                        'offer_id' => $offerId,
                        'quantity' => $row['quantity'],
                        'cost_amount' => $row['cost'],
                    ]);
                }

                event(new StockConsumed($event->orderId, $event->orderNumber, $cost, $order->currency));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
