<?php

declare(strict_types=1);

use App\Modules\Orders\Domain\OrderStatus;

it('allows the paiement-à-la-livraison happy path', function (OrderStatus $from, OrderStatus $to) {
    expect($from->canTransitionTo($to))->toBeTrue();
})->with([
    [OrderStatus::Pending, OrderStatus::Confirmed],
    [OrderStatus::Confirmed, OrderStatus::Shipped],
    [OrderStatus::Shipped, OrderStatus::Delivered],
    [OrderStatus::Pending, OrderStatus::Cancelled],
    [OrderStatus::Confirmed, OrderStatus::Cancelled],
]);

it('forbids skipping steps or reviving a terminal order', function (OrderStatus $from, OrderStatus $to) {
    expect($from->canTransitionTo($to))->toBeFalse();
})->with([
    [OrderStatus::Pending, OrderStatus::Shipped],
    [OrderStatus::Pending, OrderStatus::Delivered],
    [OrderStatus::Shipped, OrderStatus::Cancelled],
    [OrderStatus::Delivered, OrderStatus::Pending],
    [OrderStatus::Cancelled, OrderStatus::Pending],
]);

it('has no outgoing transition from delivered or cancelled (terminal states)', function (OrderStatus $status) {
    expect($status->allowedTransitions())->toBe([]);
})->with([OrderStatus::Delivered, OrderStatus::Cancelled]);
