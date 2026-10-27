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
 */
final class OpenReturnAction
{
    public function __construct(private readonly OrderLookupGatewayInterface $orders) {}

    public function execute(string $orderNumber, string $reason, ?int $amount = null, ?int $createdBy = null): ReturnRequest
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

        return DB::transaction(function () use ($order, $orderNumber, $reason, $amount, $createdBy) {
            // Serialise concurrent requests on the same order so the
            // refundable balance below cannot be over-committed.
            $committed = (int) ReturnRequest::query()
                ->where('order_number', $orderNumber)
                ->whereIn('status', [ReturnStatus::Requested, ReturnStatus::Accepted, ReturnStatus::Received])
                ->lockForUpdate()
                ->sum('requested_amount');

            $refunded = (int) ReturnRequest::query()
                ->where('order_number', $orderNumber)
                ->where('status', ReturnStatus::Refunded)
                ->sum('refunded_amount');

            $refundable = $order->subtotalAmount - $committed - $refunded;
            $amount ??= $refundable;

            if ($amount < 1 || $amount > $refundable) {
                throw ReturnException::amountExceedsRefundable(max(0, $refundable));
            }

            return ReturnRequest::query()->create([
                'order_number' => $orderNumber,
                'status' => ReturnStatus::Requested,
                'reason' => $reason,
                'currency' => $order->currency,
                'requested_amount' => $amount,
                'created_by' => $createdBy,
            ]);
        });
    }
}
