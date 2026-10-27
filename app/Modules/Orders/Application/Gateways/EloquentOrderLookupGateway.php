<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Gateways;

use App\Modules\Orders\Application\Actions\TransitionOrderStatusAction;
use App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface;
use App\Modules\Orders\Application\Data\OrderLineData;
use App\Modules\Orders\Application\Data\OrderSummaryData;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;

final class EloquentOrderLookupGateway implements OrderLookupGatewayInterface
{
    public function find(string $orderNumber): ?OrderSummaryData
    {
        $order = Order::query()->with('items')->where('order_number', $orderNumber)->first();

        return $order ? $this->toData($order) : null;
    }

    public function deliveredUntil(\DateTimeInterface $cutoff): array
    {
        return Order::query()
            ->with('items')
            ->where('status', OrderStatus::Delivered)
            ->where('delivered_at', '<=', $cutoff)
            ->get()
            ->map(fn (Order $order) => $this->toData($order))
            ->all();
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

        (new TransitionOrderStatusAction)->execute($order, $target);

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
