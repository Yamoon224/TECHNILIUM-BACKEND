<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderStatusEvent;
use App\Modules\Orders\Infrastructure\Models\SubOrder;
use App\Modules\Vendors\Infrastructure\Models\Vendor;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function historyStaff(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::OperateurCommandes->value);

    return $user;
}

function historyOrder(?User $customer = null): array
{
    $offer = Offer::factory()->create(['price_amount' => 10_000, 'stock_quantity' => 5]);

    $response = test()->actingAs($customer ?? User::factory()->create(), 'sanctum')->postJson('/api/v1/orders', [
        'items' => [['offer_id' => $offer->id, 'quantity' => 1]],
        'customer_name' => 'Awa Diallo', 'customer_phone' => '0700000001', 'delivery_address' => 'Cocody',
    ])->assertCreated();

    return [$response->json('order_number'), $response];
}

it('records the first step when an order is placed, and returns it in the creation response', function () {
    [$number, $response] = historyOrder();

    expect(OrderStatusEvent::query()->whereNull('sub_order_id')->where('order_id', Order::query()->where('order_number', $number)->value('id'))->pluck('status')->all())
        ->toBe(['pending'])
        ->and($response->json('status_history'))->toHaveCount(1)
        ->and($response->json('status_history.0.status'))->toBe('pending');
});

it('adds one entry per real status change, in order, with its date', function () {
    [$number] = historyOrder();

    foreach (['confirmed', 'shipped', 'delivered'] as $status) {
        $this->actingAs(historyStaff(), 'sanctum')->postJson("/api/v1/orders/{$number}/transition", ['status' => $status])->assertOk();
    }

    $order = Order::query()->where('order_number', $number)->first();
    expect($order->statusEvents->pluck('status')->all())->toBe(['pending', 'confirmed', 'shipped', 'delivered'])
        ->and($order->statusEvents->every(fn ($event) => $event->occurred_at !== null))->toBeTrue();
});

it('records a cancellation', function () {
    [$number] = historyOrder();

    $this->actingAs(historyStaff(), 'sanctum')->postJson("/api/v1/orders/{$number}/transition", ['status' => 'cancelled'])->assertOk();

    expect(Order::query()->where('order_number', $number)->first()->statusEvents->pluck('status')->all())->toBe(['pending', 'cancelled']);
});

it('does not log a refused transition', function () {
    [$number] = historyOrder();

    $this->actingAs(historyStaff(), 'sanctum')->postJson("/api/v1/orders/{$number}/transition", ['status' => 'delivered'])->assertUnprocessable();

    expect(Order::query()->where('order_number', $number)->first()->statusEvents)->toHaveCount(1);
});

it('logs the parent order only when its derived status really changes', function () {
    $a = Vendor::factory()->active()->create();
    $b = Vendor::factory()->active()->create();
    $order = Order::factory()->create(['status' => OrderStatus::Confirmed]);
    foreach ([$a->id, $b->id] as $vendorId) {
        SubOrder::factory()->create(['order_id' => $order->id, 'vendor_id' => $vendorId, 'status' => OrderStatus::Confirmed]);
    }
    $subs = $order->subOrders;
    $staff = historyStaff();

    $this->actingAs($staff, 'sanctum')->postJson("/api/v1/orders/{$order->order_number}/sub-orders/{$subs[0]->id}/transition", ['status' => 'shipped'])->assertOk();
    expect($order->fresh()->statusEvents)->toHaveCount(0); // one parcel moved, the order did not

    $this->actingAs($staff, 'sanctum')->postJson("/api/v1/orders/{$order->order_number}/sub-orders/{$subs[1]->id}/transition", ['status' => 'shipped'])->assertOk();
    expect($order->fresh()->statusEvents->pluck('status')->all())->toBe(['shipped']);
});

it('exposes the history to the customer on their orders and on the phone-verified lookup', function () {
    $customer = User::factory()->create();
    [$number] = historyOrder($customer);
    $this->actingAs(historyStaff(), 'sanctum')->postJson("/api/v1/orders/{$number}/transition", ['status' => 'confirmed'])->assertOk();

    $mine = $this->actingAs($customer, 'sanctum')->getJson('/api/v1/orders/mine')->assertOk()->json('data.0');
    expect(array_column($mine['status_history'], 'status'))->toBe(['pending', 'confirmed']);

    $lookup = $this->getJson("/api/v1/orders/{$number}?phone=0700000001")->assertOk();
    expect(array_column($lookup->json('status_history'), 'status'))->toBe(['pending', 'confirmed']);
});

it('shows staff the history in the order list too', function () {
    [$number] = historyOrder();

    $row = collect($this->actingAs(historyStaff(), 'sanctum')->getJson('/api/v1/orders')->assertOk()->json('data'))
        ->firstWhere('order_number', $number);

    expect(array_column($row['status_history'], 'status'))->toBe(['pending']);
});

it('deletes the history with its order', function () {
    [$number] = historyOrder();
    $order = Order::query()->where('order_number', $number)->first();

    $order->delete();

    expect(OrderStatusEvent::query()->count())->toBe(0);
});

function parcelOrder(User $customer): Order
{
    $a = Vendor::factory()->active()->create();
    $order = Order::factory()->create(['status' => OrderStatus::Confirmed, 'customer_id' => $customer->id]);
    foreach ([null, $a->id] as $vendorId) {
        $sub = SubOrder::factory()->create(['order_id' => $order->id, 'vendor_id' => $vendorId, 'status' => OrderStatus::Confirmed]);
        App\Modules\Orders\Infrastructure\Models\OrderItem::factory()->create(['order_id' => $order->id, 'sub_order_id' => $sub->id, 'vendor_id' => $vendorId, 'product_name' => 'Galaxy', 'brand' => 'Samsung', 'quantity' => 2]);
    }

    return $order->load('subOrders');
}

it('records each parcel\'s own history, from checkout through its own moves', function () {
    $customer = User::factory()->create();
    [$number] = historyOrder($customer);
    $order = Order::query()->where('order_number', $number)->first();
    $parcel = $order->subOrders()->first();

    expect($parcel->statusEvents->pluck('status')->all())->toBe(['pending']);

    $this->actingAs(historyStaff(), 'sanctum')->postJson("/api/v1/orders/{$number}/transition", ['status' => 'confirmed'])->assertOk();
    $this->actingAs(historyStaff(), 'sanctum')->postJson("/api/v1/orders/{$number}/transition", ['status' => 'shipped'])->assertOk();

    expect($parcel->fresh()->statusEvents->pluck('status')->all())->toBe(['pending', 'confirmed', 'shipped'])
        // The parent history carries only the parent's own steps.
        ->and($order->fresh()->statusEvents->pluck('status')->all())->toBe(['pending', 'confirmed', 'shipped']);
});

it('moves one parcel without touching the other parcel\'s history', function () {
    $customer = User::factory()->create();
    $order = parcelOrder($customer);
    [$first, $second] = $order->subOrders->all();

    $this->actingAs(historyStaff(), 'sanctum')->postJson("/api/v1/orders/{$order->order_number}/sub-orders/{$first->id}/transition", ['status' => 'shipped'])->assertOk();

    expect($first->fresh()->statusEvents->pluck('status')->all())->toBe(['shipped'])
        ->and($second->fresh()->statusEvents)->toHaveCount(0)
        ->and($order->fresh()->statusEvents)->toHaveCount(0);
});

it('shows a customer one of their orders with its parcels, their items and histories', function () {
    $customer = User::factory()->create();
    $order = parcelOrder($customer);
    [$first] = $order->subOrders->all();
    $this->actingAs(historyStaff(), 'sanctum')->postJson("/api/v1/orders/{$order->order_number}/sub-orders/{$first->id}/transition", ['status' => 'shipped'])->assertOk();

    $response = $this->actingAs($customer, 'sanctum')->getJson("/api/v1/orders/mine/{$order->order_number}")->assertOk();

    expect($response->json('sub_orders'))->toHaveCount(2)
        ->and($response->json('sub_orders.0.items.0.product_name'))->toBe('Galaxy')
        ->and($response->json('sub_orders.0.items.0.quantity'))->toBe(2)
        ->and(array_column($response->json('sub_orders.0.status_history'), 'status'))->toBe(['shipped'])
        ->and($response->json('partial'))->toBeTrue();
});

it('answers 404, never 403, for another customer\'s order, a guest order or an unknown number', function () {
    $owner = User::factory()->create();
    $order = parcelOrder($owner);
    $guest = Order::factory()->create(['customer_id' => null]);
    $intruder = User::factory()->create();

    $this->getJson("/api/v1/orders/mine/{$order->order_number}")->assertUnauthorized();
    $this->actingAs($intruder, 'sanctum')->getJson("/api/v1/orders/mine/{$order->order_number}")->assertNotFound();
    $this->actingAs($intruder, 'sanctum')->getJson("/api/v1/orders/mine/{$guest->order_number}")->assertNotFound();
    $this->actingAs($owner, 'sanctum')->getJson('/api/v1/orders/mine/TCH-NOPE')->assertNotFound();
});

it('lets anyone order without an account with just a name, a phone number and a delivery place', function () {
    $offer = Offer::factory()->create(['price_amount' => 10_000, 'stock_quantity' => 5]);

    $response = $this->postJson('/api/v1/orders', [
        'items' => [['offer_id' => $offer->id, 'quantity' => 1]],
        'customer_name' => 'Awa Diallo',
        'customer_phone' => '0700000001',
        'delivery_address' => 'Cocody Angré, près de la pharmacie',
    ])->assertCreated();

    $order = Order::query()->where('order_number', $response->json('order_number'))->sole();
    expect($order->customer_id)->toBeNull()
        ->and($order->customer_email)->toBeNull()
        ->and($order->delivery_city)->toBeNull()
        ->and($response->json('delivery_code'))->toMatch('/^\d{6}$/');

    // The guest follows it with the order number and the same phone.
    $this->getJson("/api/v1/orders/{$order->order_number}?phone=0700000001")->assertOk();
    $this->getJson("/api/v1/orders/{$order->order_number}?phone=0799999999")->assertNotFound();
});

it('still requires the three essentials', function () {
    $offer = Offer::factory()->create(['price_amount' => 10_000, 'stock_quantity' => 5]);
    $base = ['items' => [['offer_id' => $offer->id, 'quantity' => 1]], 'customer_name' => 'Awa', 'customer_phone' => '0700000001', 'delivery_address' => 'Cocody'];

    foreach (['customer_name', 'customer_phone', 'delivery_address'] as $field) {
        $payload = $base;
        unset($payload[$field]);

        $this->postJson('/api/v1/orders', $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
    }
});
