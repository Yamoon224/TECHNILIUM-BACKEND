<?php

declare(strict_types=1);

use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Domain\OrderStatusDeriver;

it('has no status without sub-orders', function () {
    expect(OrderStatusDeriver::derive([]))->toBeNull();
});

it('is only as advanced as the least advanced live sub-order', function (array $subs, OrderStatus $expected) {
    expect(OrderStatusDeriver::derive($subs))->toBe($expected);
})->with([
    'all delivered' => [[OrderStatus::Delivered, OrderStatus::Delivered], OrderStatus::Delivered],
    'one still pending' => [[OrderStatus::Shipped, OrderStatus::Pending], OrderStatus::Pending],
    'shipped and delivered' => [[OrderStatus::Delivered, OrderStatus::Shipped], OrderStatus::Shipped],
    'single' => [[OrderStatus::Confirmed], OrderStatus::Confirmed],
]);

it('ignores cancelled sub-orders, and is cancelled only when all are', function () {
    expect(OrderStatusDeriver::derive([OrderStatus::Cancelled, OrderStatus::Delivered]))->toBe(OrderStatus::Delivered)
        ->and(OrderStatusDeriver::derive([OrderStatus::Cancelled, OrderStatus::Cancelled]))->toBe(OrderStatus::Cancelled);
});

it('flags a partial order when live sub-orders are at different stages', function () {
    expect(OrderStatusDeriver::isPartial([OrderStatus::Shipped, OrderStatus::Pending]))->toBeTrue()
        ->and(OrderStatusDeriver::isPartial([OrderStatus::Shipped, OrderStatus::Shipped]))->toBeFalse()
        ->and(OrderStatusDeriver::isPartial([OrderStatus::Shipped, OrderStatus::Cancelled]))->toBeFalse();
});
