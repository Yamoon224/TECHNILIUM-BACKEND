<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use App\Modules\Orders\Infrastructure\Models\SubOrder;
use App\Modules\Payouts\Infrastructure\Models\Payout;
use App\Modules\Vendors\Infrastructure\Models\Vendor;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function opsUser(Role $role = Role::OperateurCommandes): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

function sellerUser(): array
{
    $user = User::factory()->create();
    $user->assignRole(Role::VendeurTiers->value);

    return [$user, Vendor::factory()->active()->create(['user_id' => $user->id])];
}

/** A two-vendor + own-stock order with one sub-order each, all at $status. */
function splitOrder(Vendor $a, Vendor $b, OrderStatus $status = OrderStatus::Confirmed): Order
{
    $order = Order::factory()->create(['status' => $status, 'subtotal_amount' => 100_000]);

    foreach ([[$a->id, 60_000], [$b->id, 30_000], [null, 10_000]] as [$vendorId, $total]) {
        $sub = SubOrder::factory()->create([
            'order_id' => $order->id, 'vendor_id' => $vendorId, 'status' => $status, 'subtotal_amount' => $total,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id, 'sub_order_id' => $sub->id, 'vendor_id' => $vendorId,
            'unit_price_amount' => $total, 'quantity' => 1, 'line_total_amount' => $total,
        ]);
    }

    return $order;
}

it('splits a checkout into one sub-order per seller', function () {
    $v1 = Vendor::factory()->active()->create();
    $v2 = Vendor::factory()->active()->create();
    $a = Offer::factory()->fromVendor($v1->id)->create(['price_amount' => 100_000, 'stock_quantity' => 5]);
    $b = Offer::factory()->fromVendor($v2->id)->create(['price_amount' => 50_000, 'stock_quantity' => 5]);
    $own1 = Offer::factory()->create(['price_amount' => 10_000, 'stock_quantity' => 5]);
    $own2 = Offer::factory()->create(['price_amount' => 20_000, 'stock_quantity' => 5]);

    $response = $this->postJson('/api/v1/orders', [
        'items' => [
            ['offer_id' => $a->id, 'quantity' => 1],
            ['offer_id' => $b->id, 'quantity' => 2],
            ['offer_id' => $own1->id, 'quantity' => 1],
            ['offer_id' => $own2->id, 'quantity' => 1],
        ],
        'customer_name' => 'Awa', 'customer_phone' => '0700000000', 'delivery_address' => 'Cocody',
    ])->assertCreated();

    $response->assertJsonCount(3, 'sub_orders')->assertJsonPath('partial', false);

    $byVendor = collect($response->json('sub_orders'))->keyBy(fn ($s) => $s['vendor_id'] ?? 'own');
    expect($byVendor[$v1->id]['subtotal']['amount'])->toBe(100_000)
        ->and($byVendor[$v2->id]['subtotal']['amount'])->toBe(100_000)
        ->and($byVendor['own']['subtotal']['amount'])->toBe(30_000);
    expect(SubOrder::query()->sum('subtotal_amount'))->toBe(230_000);
});

it('lets staff move one sub-order, deriving the parent status and flagging it partial', function () {
    $v1 = Vendor::factory()->active()->create();
    $v2 = Vendor::factory()->active()->create();
    $order = splitOrder($v1, $v2);
    $sub = $order->subOrders()->where('vendor_id', $v1->id)->first();

    $response = $this->actingAs(opsUser(), 'sanctum')
        ->postJson("/api/v1/orders/{$order->order_number}/sub-orders/{$sub->id}/transition", ['status' => 'shipped'])
        ->assertOk();

    // Parent stays at the least advanced share (confirmed) but is partial.
    $response->assertJsonPath('status', 'confirmed')->assertJsonPath('partial', true);
    expect($sub->fresh()->status)->toBe(OrderStatus::Shipped);
});

it('refuses an illegal sub-order transition and a foreign sub-order id', function () {
    $v1 = Vendor::factory()->active()->create();
    $v2 = Vendor::factory()->active()->create();
    $order = splitOrder($v1, $v2);
    $other = splitOrder($v1, $v2);
    $sub = $order->subOrders()->first();
    $user = opsUser();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/orders/{$order->order_number}/sub-orders/{$sub->id}/transition", ['status' => 'delivered'])
        ->assertUnprocessable();

    $foreign = $other->subOrders()->first();
    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/orders/{$order->order_number}/sub-orders/{$foreign->id}/transition", ['status' => 'shipped'])
        ->assertNotFound();
});

it('restricts sub-order transitions to order managers', function () {
    $v1 = Vendor::factory()->active()->create();
    $order = splitOrder($v1, Vendor::factory()->active()->create());
    $sub = $order->subOrders()->first();

    $this->actingAs(opsUser(Role::Marketing), 'sanctum')
        ->postJson("/api/v1/orders/{$order->order_number}/sub-orders/{$sub->id}/transition", ['status' => 'shipped'])
        ->assertForbidden();
});

it('derives the parent to delivered with the last sub-order and stamps delivery dates', function () {
    $v1 = Vendor::factory()->active()->create();
    $v2 = Vendor::factory()->active()->create();
    $order = splitOrder($v1, $v2, OrderStatus::Shipped);
    $user = opsUser();

    foreach ($order->subOrders as $i => $sub) {
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->order_number}/sub-orders/{$sub->id}/transition", ['status' => 'delivered'])
            ->assertOk();

        $response->assertJsonPath('status', $i < 2 ? 'shipped' : 'delivered');
    }

    expect($order->fresh()->delivered_at)->not->toBeNull()
        ->and($order->subOrders()->whereNull('delivered_at')->count())->toBe(0);
});

it('cascades a whole-order transition to every sub-order', function () {
    $order = splitOrder(Vendor::factory()->active()->create(), Vendor::factory()->active()->create(), OrderStatus::Pending);

    $this->actingAs(opsUser(), 'sanctum')
        ->postJson("/api/v1/orders/{$order->order_number}/transition", ['status' => 'confirmed'])
        ->assertOk()
        ->assertJsonPath('status', 'confirmed');

    expect($order->subOrders()->where('status', 'confirmed')->count())->toBe(3);
});

it('lets a vendor ship only its own confirmed share, leaving the others untouched', function () {
    [$user, $vendor] = sellerUser();
    $other = Vendor::factory()->active()->create();
    $order = splitOrder($vendor, $other);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/vendors/me/orders/{$order->order_number}/ship")
        ->assertOk();

    expect($order->subOrders()->where('vendor_id', $vendor->id)->first()->status)->toBe(OrderStatus::Shipped)
        ->and($order->subOrders()->where('vendor_id', $other->id)->first()->status)->toBe(OrderStatus::Confirmed)
        ->and($order->fresh()->status)->toBe(OrderStatus::Confirmed);

    // Already shipped: no second move.
    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/vendors/me/orders/{$order->order_number}/ship")
        ->assertUnprocessable();
});

it('refuses to ship an unconfirmed share, and 404s on another vendor\'s order', function () {
    [$user, $vendor] = sellerUser();
    $pending = splitOrder($vendor, Vendor::factory()->active()->create(), OrderStatus::Pending);
    $foreign = splitOrder(Vendor::factory()->active()->create(), Vendor::factory()->active()->create());

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/vendors/me/orders/{$pending->order_number}/ship")
        ->assertUnprocessable();
    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/vendors/me/orders/{$foreign->order_number}/ship")
        ->assertNotFound();
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/vendors/me/orders/TCH-NOPE/ship')
        ->assertNotFound();

    expect($foreign->subOrders()->where('status', 'shipped')->count())->toBe(0);
});

it('shows the vendor the status of its own share, not the parent\'s', function () {
    [$user, $vendor] = sellerUser();
    $order = splitOrder($vendor, Vendor::factory()->active()->create());
    $order->subOrders()->where('vendor_id', $vendor->id)->first()->update(['status' => OrderStatus::Shipped]);

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/vendors/me/orders')
        ->assertOk()
        ->assertJsonPath('data.0.status', 'shipped');
});

it('pays a vendor for its delivered share without waiting for the other vendors', function () {
    $a = Vendor::factory()->active()->create();
    $b = Vendor::factory()->active()->create();
    $order = splitOrder($a, $b, OrderStatus::Shipped);

    $mine = $order->subOrders()->where('vendor_id', $a->id)->first();
    $mine->update(['status' => OrderStatus::Delivered, 'delivered_at' => now()->subDays(10)]);
    $finance = opsUser(Role::Comptable);

    // Vendor A's share is delivered and past the window; the parent is NOT delivered.
    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);

    $this->actingAs($finance, 'sanctum')
        ->postJson('/api/v1/admin/payouts', ['vendor_id' => $a->id])
        ->assertCreated()
        ->assertJsonPath('gross_amount', 60_000)
        ->assertJsonPath('commission_amount', 6_000);

    // Vendor B's share is still shipped: nothing payable.
    $this->actingAs($finance, 'sanctum')
        ->postJson('/api/v1/admin/payouts', ['vendor_id' => $b->id])
        ->assertUnprocessable();

    expect(Payout::query()->count())->toBe(1);
});

it('only counts the vendor\'s own sub-order in a payout, never the whole order', function () {
    $a = Vendor::factory()->active()->create();
    $order = splitOrder($a, Vendor::factory()->active()->create(), OrderStatus::Delivered);
    $order->subOrders()->update(['delivered_at' => now()->subDays(10)]);
    $order->update(['delivered_at' => now()->subDays(10)]);

    $this->actingAs(opsUser(Role::Comptable), 'sanctum')
        ->postJson('/api/v1/admin/payouts', ['vendor_id' => $a->id])
        ->assertCreated()
        ->assertJsonPath('gross_amount', 60_000);
});
