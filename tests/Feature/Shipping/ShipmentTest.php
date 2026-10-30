<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Shipping\Infrastructure\Models\Shipment;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function logisticien(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Logistique->value);

    return $user;
}

it('rejects an anonymous request to list shipments', function () {
    $this->getJson('/api/v1/admin/shipments')->assertUnauthorized();
});

it('rejects roles other than logistique from managing shipments', function (Role $role) {
    $user = User::factory()->create();
    $user->assignRole($role->value);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/admin/shipments')
        ->assertForbidden();
})->with([
    Role::Comptable,
    Role::GestionnaireStock,
    Role::VendeurTiers,
    Role::Client,
]);

it('creates a shipment that snapshots the order\'s delivery details', function () {
    $order = Order::factory()->create([
        'customer_name' => 'Awa Diallo',
        'customer_phone' => '0700000000',
        'delivery_address' => 'Cocody Angré',
        'delivery_city' => 'Abidjan',
    ]);

    $response = $this->actingAs(logisticien(), 'sanctum')
        ->postJson('/api/v1/admin/shipments', ['order_number' => $order->order_number]);

    $response->assertCreated();
    $response->assertJsonPath('customer_name', 'Awa Diallo');
    $response->assertJsonPath('delivery_address', 'Cocody Angré');
    $response->assertJsonPath('status', 'pending');
    $response->assertJsonPath('attempts', 0);
});

it('rejects a shipment for an unknown order', function () {
    $this->actingAs(logisticien(), 'sanctum')
        ->postJson('/api/v1/admin/shipments', ['order_number' => 'TCH-DOES-NOT-EXIST'])
        ->assertUnprocessable();
});

it('refuses to create a second shipment for the same order', function () {
    $order = Order::factory()->create();
    Shipment::factory()->create(['order_number' => $order->order_number]);

    $this->actingAs(logisticien(), 'sanctum')
        ->postJson('/api/v1/admin/shipments', ['order_number' => $order->order_number])
        ->assertUnprocessable();
});

it('assigns a livreur to a shipment', function () {
    $shipment = Shipment::factory()->create();
    $livreur = logisticien();

    $response = $this->actingAs(logisticien(), 'sanctum')
        ->postJson("/api/v1/admin/shipments/{$shipment->id}/assign", ['assigned_to' => $livreur->id]);

    $response->assertOk();
    $response->assertJsonPath('assigned_to', $livreur->id);
});

it('starts a pending shipment and syncs the order to shipped', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Confirmed]);
    $shipment = Shipment::factory()->create(['order_number' => $order->order_number]);

    $response = $this->actingAs(logisticien(), 'sanctum')
        ->postJson("/api/v1/admin/shipments/{$shipment->id}/start");

    $response->assertOk();
    $response->assertJsonPath('status', 'in_transit');
    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
});

it('delivers an in-transit shipment and syncs the order to delivered', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Shipped, 'delivery_code' => '123456']);
    $shipment = Shipment::factory()->inTransit()->create(['order_number' => $order->order_number]);

    $response = $this->actingAs(logisticien(), 'sanctum')
        ->postJson("/api/v1/admin/shipments/{$shipment->id}/deliver", ['confirmation_code' => '123456']);

    $response->assertOk();
    $response->assertJsonPath('status', 'delivered');
    expect($shipment->fresh()->delivered_at)->not->toBeNull();
    expect($order->fresh()->status)->toBe(OrderStatus::Delivered);
});

it('does not fail when the order cannot be synced to the shipment\'s new status', function () {
    // Order stuck at Pending (never confirmed) - markShipped() is a no-op,
    // but the shipment itself still transitions.
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);
    $shipment = Shipment::factory()->create(['order_number' => $order->order_number]);

    $response = $this->actingAs(logisticien(), 'sanctum')
        ->postJson("/api/v1/admin/shipments/{$shipment->id}/start");

    $response->assertOk();
    $response->assertJsonPath('status', 'in_transit');
    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('records a failed delivery attempt with notes and increments the attempt counter', function () {
    $shipment = Shipment::factory()->inTransit()->create();

    $response = $this->actingAs(logisticien(), 'sanctum')
        ->postJson("/api/v1/admin/shipments/{$shipment->id}/fail", ['notes' => 'Client absent']);

    $response->assertOk();
    $response->assertJsonPath('status', 'failed_attempt');
    $response->assertJsonPath('attempts', 1);
    $response->assertJsonPath('notes', 'Client absent');
});

it('retries a failed attempt', function () {
    $shipment = Shipment::factory()->failedAttempt()->create();

    $this->actingAs(logisticien(), 'sanctum')
        ->postJson("/api/v1/admin/shipments/{$shipment->id}/retry")
        ->assertOk()
        ->assertJsonPath('status', 'in_transit');
});

it('returns a shipment to sender after a failed attempt', function () {
    $shipment = Shipment::factory()->failedAttempt()->create();

    $this->actingAs(logisticien(), 'sanctum')
        ->postJson("/api/v1/admin/shipments/{$shipment->id}/return")
        ->assertOk()
        ->assertJsonPath('status', 'returned');
});

it('refuses to deliver a shipment that was never dispatched', function () {
    $shipment = Shipment::factory()->create(); // pending

    $this->actingAs(logisticien(), 'sanctum')
        ->postJson("/api/v1/admin/shipments/{$shipment->id}/deliver", ['confirmation_code' => '123456'])
        ->assertStatus(422);
});

it('filters shipments by status', function () {
    Shipment::factory()->create();
    Shipment::factory()->delivered()->create();

    $response = $this->actingAs(logisticien(), 'sanctum')
        ->getJson('/api/v1/admin/shipments?status=delivered');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.status', 'delivered');
});

it('caps per_page and clamps a non-positive value to 1', function () {
    Shipment::factory()->count(3)->create();

    $this->actingAs(logisticien(), 'sanctum')
        ->getJson('/api/v1/admin/shipments?per_page=999999')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);

    $this->actingAs(logisticien(), 'sanctum')
        ->getJson('/api/v1/admin/shipments?per_page=-5')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 1);
});
