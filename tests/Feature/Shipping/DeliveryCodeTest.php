<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Shipping\Infrastructure\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function courier(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Logistique->value);

    return $user;
}

/** @return array{Order, Shipment} */
function inTransitWithCode(?string $code = '482913'): array
{
    $order = Order::factory()->create(['status' => OrderStatus::Shipped, 'delivery_code' => $code]);

    return [$order, Shipment::factory()->inTransit()->create(['order_number' => $order->order_number])];
}

it('generates a six-digit code at checkout and shows it to the customer only', function () {
    $offer = Offer::factory()->create(['price_amount' => 10_000, 'stock_quantity' => 5]);

    $created = $this->postJson('/api/v1/orders', [
        'items' => [['offer_id' => $offer->id, 'quantity' => 1]],
        'customer_name' => 'Awa', 'customer_phone' => '0700000000', 'delivery_address' => 'Cocody',
    ])->assertCreated();

    expect($created->json('delivery_code'))->toMatch('/^\d{6}$/');

    $orderNumber = $created->json('order_number');
    $this->getJson("/api/v1/orders/{$orderNumber}?phone=0700000000")
        ->assertOk()
        ->assertJsonPath('delivery_code', $created->json('delivery_code'));

    // Staff and couriers never see it.
    $staff = User::factory()->create();
    $staff->assignRole(Role::OperateurCommandes->value);
    $this->actingAs($staff, 'sanctum')->getJson('/api/v1/orders')
        ->assertOk()
        ->assertJsonMissingPath('data.0.delivery_code');
});

it('stores the code encrypted at rest', function () {
    $order = Order::factory()->create(['delivery_code' => '482913']);

    expect(DB::table('orders')->where('id', $order->id)->value('delivery_code'))->not->toContain('482913')
        ->and($order->fresh()->delivery_code)->toBe('482913');
});

it('delivers with the right code and syncs the order', function () {
    [$order, $shipment] = inTransitWithCode();

    $this->actingAs(courier(), 'sanctum')
        ->postJson("/api/v1/admin/shipments/{$shipment->id}/deliver", ['confirmation_code' => '482913'])
        ->assertOk()
        ->assertJsonPath('status', 'delivered');

    expect($order->fresh()->status)->toBe(OrderStatus::Delivered);
});

it('requires a code to deliver', function () {
    [, $shipment] = inTransitWithCode();

    $this->actingAs(courier(), 'sanctum')
        ->postJson("/api/v1/admin/shipments/{$shipment->id}/deliver")
        ->assertUnprocessable();
});

it('refuses a wrong code, counts the attempt and leaves everything undelivered', function () {
    [$order, $shipment] = inTransitWithCode();

    $this->actingAs(courier(), 'sanctum')
        ->postJson("/api/v1/admin/shipments/{$shipment->id}/deliver", ['confirmation_code' => '000000'])
        ->assertUnprocessable()
        ->assertJsonPath('message', fn (string $m) => str_contains($m, '4'));

    expect($shipment->fresh()->code_attempts)->toBe(1)
        ->and($shipment->fresh()->status->value)->toBe('in_transit')
        ->and($order->fresh()->status)->toBe(OrderStatus::Shipped);
});

it('locks the shipment after five wrong codes, even for the right one', function () {
    [, $shipment] = inTransitWithCode();
    $user = courier();

    foreach (range(1, 5) as $_) {
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/admin/shipments/{$shipment->id}/deliver", ['confirmation_code' => '000000'])
            ->assertUnprocessable();
    }

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/admin/shipments/{$shipment->id}/deliver", ['confirmation_code' => '482913'])
        ->assertUnprocessable()
        ->assertJsonPath('message', fn (string $m) => str_contains($m, 'verrouill'));

    expect($shipment->fresh()->status->value)->toBe('in_transit');
    $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/shipments')->assertJsonPath('data.0.code_locked', true);
});

it('does not burn an attempt when the shipment is not in transit', function () {
    $order = Order::factory()->create(['delivery_code' => '482913']);
    $shipment = Shipment::factory()->create(['order_number' => $order->order_number]); // pending

    $this->actingAs(courier(), 'sanctum')
        ->postJson("/api/v1/admin/shipments/{$shipment->id}/deliver", ['confirmation_code' => '000000'])
        ->assertUnprocessable();

    expect($shipment->fresh()->code_attempts)->toBe(0);
});

it('accepts an order placed before codes existed', function () {
    [, $shipment] = inTransitWithCode(code: null);

    $this->actingAs(courier(), 'sanctum')
        ->postJson("/api/v1/admin/shipments/{$shipment->id}/deliver", ['confirmation_code' => 'anything'])
        ->assertOk();
});
