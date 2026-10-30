<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function backofficeUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::OperateurCommandes->value);

    return $user;
}

it('rejects an anonymous request to list orders', function () {
    $this->getJson('/api/v1/orders')->assertUnauthorized();
});

it('rejects a client-role user from listing orders', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::Client->value);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/orders')
        ->assertForbidden();
});

it('rejects roles that are backoffice-adjacent but not order management', function (Role $role) {
    $user = User::factory()->create();
    $user->assignRole($role->value);

    // Vendeur tiers especially: an external party, must never see every
    // order just by not being a "client" (cahier §4 - their own orders
    // only, through a future vendor-scoped endpoint, not this one).
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/orders')
        ->assertForbidden();
})->with([
    Role::VendeurTiers,
    Role::Marketing,
    Role::GestionnaireCatalogue,
    Role::GestionnaireStock,
]);

it('lists orders for an authenticated backoffice user', function () {
    Order::factory()->count(3)->create();

    $this->actingAs(backofficeUser(), 'sanctum')
        ->getJson('/api/v1/orders')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('filters the list by status', function () {
    Order::factory()->create(['status' => OrderStatus::Pending]);
    Order::factory()->create(['status' => OrderStatus::Delivered]);

    $response = $this->actingAs(backofficeUser(), 'sanctum')
        ->getJson('/api/v1/orders?status=delivered');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.status', 'delivered');
});

it('caps per_page so a client cannot force an unbounded query', function () {
    Order::factory()->count(3)->create();

    $this->actingAs(backofficeUser(), 'sanctum')
        ->getJson('/api/v1/orders?per_page=999999')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);
});

it('clamps a non-positive per_page to 1 rather than erroring or ignoring it', function (int $perPage) {
    Order::factory()->count(3)->create();

    $this->actingAs(backofficeUser(), 'sanctum')
        ->getJson("/api/v1/orders?per_page={$perPage}")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 1);
})->with([0, -1, -999]);

it('rejects an anonymous request to transition an order', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $this->postJson("/api/v1/orders/{$order->order_number}/transition", ['status' => 'confirmed'])
        ->assertUnauthorized();
});

it('rejects a client-role user from transitioning an order', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::Client->value);
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/orders/{$order->order_number}/transition", ['status' => 'confirmed'])
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('lets an authenticated backoffice user confirm a pending order', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $response = $this->actingAs(backofficeUser(), 'sanctum')
        ->postJson("/api/v1/orders/{$order->order_number}/transition", ['status' => 'confirmed']);

    $response->assertOk();
    $response->assertJsonPath('status', 'confirmed');
    expect($order->fresh()->status)->toBe(OrderStatus::Confirmed);
});

it('refuses an illegal transition', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $response = $this->actingAs(backofficeUser(), 'sanctum')
        ->postJson("/api/v1/orders/{$order->order_number}/transition", ['status' => 'delivered']);

    $response->assertStatus(422);
    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('rejects an unknown status value', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $this->actingAs(backofficeUser(), 'sanctum')
        ->postJson("/api/v1/orders/{$order->order_number}/transition", ['status' => 'teleported'])
        ->assertUnprocessable();
});

it('returns 404 when transitioning an unknown order', function () {
    $this->actingAs(backofficeUser(), 'sanctum')
        ->postJson('/api/v1/orders/TCH-DOES-NOT-EXIST/transition', ['status' => 'confirmed'])
        ->assertNotFound();
});
