<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;

it('rejects an anonymous request to list orders', function () {
    $this->getJson('/api/v1/orders')->assertUnauthorized();
});

it('lists orders for an authenticated backoffice user', function () {
    $user = User::factory()->create();
    Order::factory()->count(3)->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/orders')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('filters the list by status', function () {
    $user = User::factory()->create();
    Order::factory()->create(['status' => OrderStatus::Pending]);
    Order::factory()->create(['status' => OrderStatus::Delivered]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/orders?status=delivered');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.status', 'delivered');
});

it('rejects an anonymous request to transition an order', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $this->postJson("/api/v1/orders/{$order->order_number}/transition", ['status' => 'confirmed'])
        ->assertUnauthorized();
});

it('lets an authenticated user confirm a pending order', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/orders/{$order->order_number}/transition", ['status' => 'confirmed']);

    $response->assertOk();
    $response->assertJsonPath('status', 'confirmed');
    expect($order->fresh()->status)->toBe(OrderStatus::Confirmed);
});

it('refuses an illegal transition', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/orders/{$order->order_number}/transition", ['status' => 'delivered']);

    $response->assertStatus(422);
    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('rejects an unknown status value', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/orders/{$order->order_number}/transition", ['status' => 'teleported'])
        ->assertUnprocessable();
});

it('returns 404 when transitioning an unknown order', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/orders/TCH-DOES-NOT-EXIST/transition', ['status' => 'confirmed'])
        ->assertNotFound();
});
