<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Listeners;

use App\Modules\Notifications\Application\NotifyCustomerAction;
use App\Modules\Notifications\Domain\MessageTemplates;
use App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface;
use App\Modules\Shared\Events\OrderPlaced;
use App\Modules\Shared\Events\OrderStatusChanged;
use App\Modules\Shared\Events\ReturnDecided;
use App\Modules\Shared\Events\ReturnRefunded;
use Throwable;

/**
 * Turns domain events into customer messages. Every handler swallows (and
 * reports) its own failures: telling the customer is a side effect and must
 * never roll back or break the order, payment or refund that triggered it.
 */
final class NotifyOnOrderEvents
{
    public function __construct(
        private readonly NotifyCustomerAction $notify,
        private readonly OrderLookupGatewayInterface $orders,
    ) {}

    public function onOrderPlaced(OrderPlaced $event): void
    {
        $this->safely(fn () => $this->notify->execute(
            'order.placed',
            $event->orderNumber,
            $event->customerPhone,
            MessageTemplates::orderPlaced($event->customerName, $event->orderNumber, $event->totalAmount, $event->currency, $event->deliveryCode, $event->locale),
            $event->customerEmail,
        ));
    }

    public function onOrderStatusChanged(OrderStatusChanged $event): void
    {
        $body = MessageTemplates::orderStatus($event->customerName, $event->orderNumber, $event->status, $event->locale);

        if ($body === null) {
            return;
        }

        $this->safely(fn () => $this->notify->execute("order.{$event->status}", $event->orderNumber, $event->customerPhone, $body, $event->customerEmail));
    }

    public function onReturnRefunded(ReturnRefunded $event): void
    {
        $this->safely(function () use ($event) {
            // The event carries no phone: ask Orders through its public contract.
            $order = $this->orders->find($event->orderNumber);

            if ($order === null) {
                return;
            }

            $this->notify->execute(
                'return.refunded',
                "{$event->orderNumber}#{$event->returnId}",
                $order->customerPhone,
                MessageTemplates::returnRefunded($event->orderNumber, $event->amount, $event->currency, $order->customerLocale),
                $order->customerEmail,
            );
        });
    }

    public function onReturnDecided(ReturnDecided $event): void
    {
        $this->safely(function () use ($event) {
            $order = $this->orders->find($event->orderNumber);

            if ($order === null) {
                return;
            }

            $body = MessageTemplates::returnDecided($event->returnId, $event->orderNumber, $event->status, $event->note, $order->customerLocale);

            if ($body === null) {
                return;
            }

            $this->notify->execute(
                "return.{$event->status}",
                "{$event->orderNumber}#{$event->returnId}",
                $order->customerPhone,
                $body,
                $order->customerEmail,
            );
        });
    }

    private function safely(callable $work): void
    {
        try {
            $work();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
