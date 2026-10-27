<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Application\Listeners;

use App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface;
use App\Modules\Procurement\Application\Contracts\OfferCostInterface;
use App\Modules\Procurement\Infrastructure\Models\CostReversal;
use App\Modules\Procurement\Infrastructure\Models\OrderCost;
use App\Modules\Shared\Events\ReturnReceived;
use App\Modules\Shared\Events\StockReturned;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Returned goods judged resellable go back into stock (Inventory does that);
 * this gives back the cost of sales recognised for them, so the stock account
 * is not left under-stated (cahier §6.9 "concordance stock <-> comptabilité").
 *
 * The amount is the average cost of the returned own-stock units, capped to
 * what was recognised for the order minus what earlier returns already gave
 * back. Third-party vendors' lines were never our cost. One reversal per
 * return, so a replayed event changes nothing.
 *
 * Side effect: it must never block the return, so failures are reported, not thrown.
 */
final class ReverseCostOfGoodsSold
{
    public function __construct(
        private readonly OrderLookupGatewayInterface $orders,
        private readonly OfferCostInterface $costs,
    ) {}

    public function handle(ReturnReceived $event): void
    {
        try {
            $recognised = OrderCost::query()->where('order_number', $event->orderNumber)->first();
            $order = $this->orders->find($event->orderNumber);

            if ($recognised === null || $order === null) {
                return; // nothing was ever recognised for this order
            }

            $ownLines = [];
            foreach ($order->lines as $line) {
                if ($line->vendorId === null && $line->offerId !== null) {
                    $ownLines[$line->offerId] = true;
                }
            }

            $cost = 0;
            foreach ($event->items as $item) {
                if (isset($ownLines[$item['offer_id']])) {
                    $cost += $this->costs->averageCost($item['offer_id']) * $item['quantity'];
                }
            }

            if ($cost <= 0) {
                return;
            }

            $amount = DB::transaction(function () use ($recognised, $event, $cost) {
                $alreadyGivenBack = (int) CostReversal::query()->where('order_id', $recognised->order_id)->sum('amount');
                $amount = min($cost, max(0, $recognised->cost_amount - $alreadyGivenBack));

                if ($amount <= 0) {
                    return 0;
                }

                CostReversal::query()->create([
                    'return_id' => $event->returnId,
                    'order_id' => $recognised->order_id,
                    'amount' => $amount,
                ]);

                return $amount;
            });

            if ($amount > 0) {
                event(new StockReturned($event->returnId, $recognised->order_id, $event->orderNumber, $amount, $order->currency));
            }
        } catch (UniqueConstraintViolationException) {
            // This return already gave its cost back (replayed event): nothing more to do.
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
