<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Gateways;

use App\Modules\Orders\Application\Actions\TransitionOrderStatusAction;
use App\Modules\Orders\Application\Actions\TransitionSubOrderAction;
use App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface;
use App\Modules\Orders\Application\Data\OrderLineData;
use App\Modules\Orders\Application\Data\OrderSummaryData;
use App\Modules\Orders\Application\Data\VendorShareData;
use App\Modules\Orders\Domain\Exceptions\InvalidOrderTransitionException;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use App\Modules\Orders\Infrastructure\Models\SubOrder;

final class EloquentOrderLookupGateway implements OrderLookupGatewayInterface
{
    public function __construct(
        private readonly TransitionOrderStatusAction $transitionOrder,
        private readonly TransitionSubOrderAction $transitionSubOrder,
    ) {}

    public function find(string $orderNumber): ?OrderSummaryData
    {
        $order = Order::query()->with('items')->where('order_number', $orderNumber)->first();

        return $order ? $this->toData($order) : null;
    }

    public function deliveredVendorSharesUntil(int $vendorId, \DateTimeInterface $cutoff): array
    {
        $shares = [];

        $delivered = SubOrder::query()
            ->with('order')
            ->where('vendor_id', $vendorId)
            ->where('status', OrderStatus::Delivered)
            ->where('delivered_at', '<=', $cutoff)
            ->get();

        foreach ($delivered as $subOrder) {
            $shares[] = new VendorShareData(
                $subOrder->order->order_number,
                $vendorId,
                $subOrder->currency,
                $subOrder->subtotal_amount,
                $subOrder->order->subtotal_amount,
            );
        }

        // Orders placed before sub-orders existed: the delivered order itself.
        $legacy = Order::query()
            ->with(['items' => fn ($query) => $query->where('vendor_id', $vendorId)])
            ->whereDoesntHave('subOrders')
            ->whereHas('items', fn ($query) => $query->where('vendor_id', $vendorId))
            ->where('status', OrderStatus::Delivered)
            ->where('delivered_at', '<=', $cutoff)
            ->get();

        foreach ($legacy as $order) {
            $shares[] = new VendorShareData(
                $order->order_number,
                $vendorId,
                $order->currency,
                (int) $order->items->sum('line_total_amount'),
                $order->subtotal_amount,
            );
        }

        return $shares;
    }

    public function forVendor(int $vendorId, int $limit = 200): array
    {
        return Order::query()
            ->whereHas('items', fn ($query) => $query->where('vendor_id', $vendorId))
            ->with([
                'items' => fn ($query) => $query->where('vendor_id', $vendorId),
                'subOrders' => fn ($query) => $query->where('vendor_id', $vendorId),
            ])
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (Order $order) {
                $data = $this->toData($order);
                $own = $order->subOrders->first();

                // The vendor sees the state of its own share, not the parent's.
                return $own === null ? $data : new OrderSummaryData(
                    $data->orderNumber, $data->customerName, $data->customerPhone, $data->deliveryAddress,
                    $data->deliveryCity, $own->status->value, $data->currency, $data->subtotalAmount,
                    $data->lines, $own->delivered_at?->toIso8601String(),
                );
            })
            ->all();
    }

    public function shipForVendor(string $orderNumber, int $vendorId): bool
    {
        $subOrder = SubOrder::query()
            ->where('vendor_id', $vendorId)
            ->whereHas('order', fn ($query) => $query->where('order_number', $orderNumber))
            ->first();

        if ($subOrder === null) {
            return false;
        }

        try {
            $this->transitionSubOrder->execute($subOrder, OrderStatus::Shipped);
        } catch (InvalidOrderTransitionException) {
            return false;
        }

        return true;
    }

    public function confirmDeliveryCode(string $orderNumber, string $code): bool
    {
        $order = Order::query()->where('order_number', $orderNumber)->first();

        if ($order === null) {
            return false;
        }

        return $order->delivery_code === null || hash_equals($order->delivery_code, $code);
    }

    public function markShipped(string $orderNumber): bool
    {
        return $this->transition($orderNumber, OrderStatus::Shipped);
    }

    public function markDelivered(string $orderNumber): bool
    {
        return $this->transition($orderNumber, OrderStatus::Delivered);
    }

    private function transition(string $orderNumber, OrderStatus $target): bool
    {
        $order = Order::query()->where('order_number', $orderNumber)->first();

        if (! $order || ! $order->status->canTransitionTo($target)) {
            return false;
        }

        $this->transitionOrder->execute($order, $target);

        return true;
    }

    private function toData(Order $order): OrderSummaryData
    {
        return new OrderSummaryData(
            orderNumber: $order->order_number,
            customerName: $order->customer_name,
            customerPhone: $order->customer_phone,
            deliveryAddress: $order->delivery_address,
            deliveryCity: $order->delivery_city,
            status: $order->status->value,
            currency: $order->currency,
            subtotalAmount: $order->subtotal_amount,
            lines: $order->items->map(fn (OrderItem $item) => new OrderLineData(
                description: trim($item->brand.' '.$item->product_name),
                quantity: $item->quantity,
                unitPriceAmount: $item->unit_price_amount,
                lineTotalAmount: $item->line_total_amount,
                vendorId: $item->vendor_id,
            ))->all(),
            deliveredAt: $order->delivered_at?->toIso8601String(),
        );
    }
}
