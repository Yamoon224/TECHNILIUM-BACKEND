<?php

declare(strict_types=1);

namespace App\Modules\Returns\Application\Actions;

use App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface;
use App\Modules\Returns\Domain\Exceptions\ReturnException;
use App\Modules\Returns\Domain\ReturnStatus;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Opens an RMA on a delivered order, inside the return window, for at most
 * what is still refundable (order total − refunded − already requested).
 * Named articles are checked against what the order actually sold and what
 * earlier returns already claimed.
 */
final class OpenReturnAction
{
    public function __construct(private readonly OrderLookupGatewayInterface $orders) {}

    /**
     * @param  list<array{offer_id: int, quantity: int}>|null  $items
     */
    public function execute(string $orderNumber, string $reason, ?int $amount = null, ?int $createdBy = null, ?array $items = null): ReturnRequest
    {
        $order = $this->orders->find($orderNumber)
            ?? throw ReturnException::orderNotFound($orderNumber);

        if ($order->status !== 'delivered') {
            throw ReturnException::orderNotDelivered($orderNumber);
        }

        $windowDays = (int) config('returns.window_days');

        if ($order->deliveredAt !== null && Carbon::parse($order->deliveredAt)->addDays($windowDays)->isPast()) {
            throw ReturnException::windowExpired($orderNumber, $windowDays);
        }

        return DB::transaction(function () use ($order, $orderNumber, $reason, $amount, $createdBy, $items) {
            // Serialise concurrent requests on the same order so the
            // refundable balance below cannot be over-committed.
            $active = ReturnRequest::query()
                ->where('order_number', $orderNumber)
                ->whereIn('status', [ReturnStatus::Requested, ReturnStatus::Accepted, ReturnStatus::Received, ReturnStatus::Refunded])
                ->lockForUpdate()
                ->get();

            $committed = (int) $active->filter(fn (ReturnRequest $r) => $r->status->isOpen())->sum('requested_amount');
            $refunded = (int) $active->where('status', ReturnStatus::Refunded)->sum('refunded_amount');
            $refundable = $order->subtotalAmount - $committed - $refunded;

            $itemsValue = null;

            if ($items !== null && $items !== []) {
                $itemsValue = $this->validatedItemsValue($order->lines, $items, $active->all());
            }

            $amount ??= $itemsValue !== null ? min($itemsValue, $refundable) : $refundable;

            if ($amount < 1 || $amount > $refundable) {
                throw ReturnException::amountExceedsRefundable(max(0, $refundable));
            }

            return ReturnRequest::query()->create([
                'order_number' => $orderNumber,
                'status' => ReturnStatus::Requested,
                'reason' => $reason,
                'currency' => $order->currency,
                'requested_amount' => $amount,
                'items' => $items === [] ? null : $items,
                'created_by' => $createdBy,
            ]);
        });
    }

    /**
     * Each returned quantity must not exceed what the order sold minus what
     * other (not rejected) returns already claim. Returns the items' value.
     *
     * @param  list<\App\Modules\Orders\Application\Data\OrderLineData>  $lines
     * @param  list<array{offer_id: int, quantity: int}>  $items
     * @param  list<ReturnRequest>  $others
     */
    private function validatedItemsValue(array $lines, array $items, array $others): int
    {
        $sold = [];
        $unitPrice = [];

        foreach ($lines as $line) {
            if ($line->offerId === null) {
                continue;
            }
            $sold[$line->offerId] = ($sold[$line->offerId] ?? 0) + $line->quantity;
            $unitPrice[$line->offerId] = $line->unitPriceAmount;
        }

        $claimed = [];
        foreach ($others as $other) {
            foreach ($other->items ?? [] as $claim) {
                $claimed[$claim['offer_id']] = ($claimed[$claim['offer_id']] ?? 0) + $claim['quantity'];
            }
        }

        $asked = [];
        $value = 0;

        foreach ($items as $item) {
            $offerId = $item['offer_id'];
            $asked[$offerId] = ($asked[$offerId] ?? 0) + $item['quantity'];
            $available = ($sold[$offerId] ?? 0) - ($claimed[$offerId] ?? 0);

            if ($item['quantity'] < 1 || $asked[$offerId] > $available) {
                throw ReturnException::itemNotReturnable($offerId, max(0, $available));
            }

            $value += $unitPrice[$offerId] * $item['quantity'];
        }

        return $value;
    }
}
