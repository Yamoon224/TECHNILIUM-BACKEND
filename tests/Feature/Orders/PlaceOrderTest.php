<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\OfferStatus;
use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Orders\Infrastructure\Models\Order;

function checkoutPayload(array $items, array $overrides = []): array
{
    return array_merge([
        'items' => $items,
        'customer_name' => 'Awa Diallo',
        'customer_phone' => '0700000000',
        'delivery_address' => 'Cocody, non loin de la pharmacie',
        'delivery_city' => 'Abidjan',
    ], $overrides);
}

it('places an order, snapshots the offer, and decrements stock', function () {
    $offer = Offer::factory()->create([
        'price_amount' => 185_000,
        'currency' => 'XOF',
        'stock_quantity' => 5,
        'status' => OfferStatus::Published,
    ]);

    $response = $this->postJson('/api/v1/orders', checkoutPayload([
        ['offer_id' => $offer->id, 'quantity' => 2],
    ]));

    $response->assertCreated();
    $response->assertJsonPath('status', 'pending');
    $response->assertJsonPath('payment_method', 'cod');
    $response->assertJsonPath('subtotal.amount', 370_000);
    $response->assertJsonPath('items.0.quantity', 2);
    $response->assertJsonPath('items.0.unit_price.amount', 185_000);

    expect($offer->fresh()->stock_quantity)->toBe(3);
    expect(Order::query()->count())->toBe(1);
});

it('sums multiple lines into the subtotal', function () {
    $a = Offer::factory()->create(['price_amount' => 100_000, 'stock_quantity' => 5]);
    $b = Offer::factory()->create(['price_amount' => 50_000, 'stock_quantity' => 5]);

    $response = $this->postJson('/api/v1/orders', checkoutPayload([
        ['offer_id' => $a->id, 'quantity' => 2],
        ['offer_id' => $b->id, 'quantity' => 3],
    ]));

    $response->assertCreated();
    $response->assertJsonPath('subtotal.amount', 100_000 * 2 + 50_000 * 3);
    $response->assertJsonCount(2, 'items');
});

it('rejects an empty cart', function () {
    $this->postJson('/api/v1/orders', checkoutPayload([]))
        ->assertUnprocessable();
});

it('rejects checkout for an offer that does not exist', function () {
    $response = $this->postJson('/api/v1/orders', checkoutPayload([
        ['offer_id' => 999_999, 'quantity' => 1],
    ]));

    $response->assertStatus(422);
    expect(Order::query()->count())->toBe(0);
});

it('rejects checkout for an unpublished offer', function () {
    $offer = Offer::factory()->create(['status' => OfferStatus::Draft, 'stock_quantity' => 10]);

    $this->postJson('/api/v1/orders', checkoutPayload([
        ['offer_id' => $offer->id, 'quantity' => 1],
    ]))->assertStatus(422);
});

it('refuses to oversell and rolls back the whole order', function () {
    $offer = Offer::factory()->create(['stock_quantity' => 1]);

    $response = $this->postJson('/api/v1/orders', checkoutPayload([
        ['offer_id' => $offer->id, 'quantity' => 5],
    ]));

    $response->assertStatus(422);
    expect($offer->fresh()->stock_quantity)->toBe(1);
    expect(Order::query()->count())->toBe(0);
});

it('rolls back the entire order if any single line fails', function () {
    $ok = Offer::factory()->create(['stock_quantity' => 10]);
    $short = Offer::factory()->create(['stock_quantity' => 1]);

    $this->postJson('/api/v1/orders', checkoutPayload([
        ['offer_id' => $ok->id, 'quantity' => 2],
        ['offer_id' => $short->id, 'quantity' => 5],
    ]))->assertStatus(422);

    expect($ok->fresh()->stock_quantity)->toBe(10);
    expect(Order::query()->count())->toBe(0);
});

it('looks up a placed order by its order number and matching phone', function () {
    $offer = Offer::factory()->create(['stock_quantity' => 5]);

    $created = $this->postJson('/api/v1/orders', checkoutPayload([
        ['offer_id' => $offer->id, 'quantity' => 1],
    ]))->json();

    $this->getJson("/api/v1/orders/{$created['order_number']}?phone=0700000000")
        ->assertOk()
        ->assertJsonPath('order_number', $created['order_number']);
});

it('returns 404 for an unknown order number', function () {
    $this->getJson('/api/v1/orders/TCH-DOES-NOT-EXIST?phone=0700000000')->assertNotFound();
});

it('refuses to reveal an order to a request with the wrong phone (IDOR)', function () {
    $offer = Offer::factory()->create(['stock_quantity' => 5]);

    $created = $this->postJson('/api/v1/orders', checkoutPayload([
        ['offer_id' => $offer->id, 'quantity' => 1],
    ]))->json();

    // Same status as a non-existent order — must not confirm the order exists.
    $this->getJson("/api/v1/orders/{$created['order_number']}?phone=0799999999")
        ->assertNotFound();
});

it('requires a phone to look up an order', function () {
    $offer = Offer::factory()->create(['stock_quantity' => 5]);

    $created = $this->postJson('/api/v1/orders', checkoutPayload([
        ['offer_id' => $offer->id, 'quantity' => 1],
    ]))->json();

    $this->getJson("/api/v1/orders/{$created['order_number']}")->assertUnprocessable();
});
