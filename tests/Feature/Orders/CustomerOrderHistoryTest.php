<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Infrastructure\Models\Order;

it('rejects an anonymous request to list my orders', function () {
    $this->getJson('/api/v1/orders/mine')->assertUnauthorized();
});

it("lists only the authenticated customer's own orders", function () {
    $me = User::factory()->create();
    $someoneElse = User::factory()->create();

    Order::factory()->count(2)->create(['customer_id' => $me->id]);
    Order::factory()->create(['customer_id' => $someoneElse->id]);
    Order::factory()->create(['customer_id' => null]); // guest order, not mine either

    $response = $this->actingAs($me, 'sanctum')->getJson('/api/v1/orders/mine');

    $response->assertOk();
    $response->assertJsonCount(2, 'data');
});

it('returns an empty list for a customer with no orders yet', function () {
    $me = User::factory()->create();

    $this->actingAs($me, 'sanctum')
        ->getJson('/api/v1/orders/mine')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
