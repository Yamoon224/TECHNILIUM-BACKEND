<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Notifications\Domain\NotificationStatus;
use App\Modules\Notifications\Infrastructure\Models\Notification;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\SubOrder;
use App\Modules\Returns\Domain\ReturnStatus;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use App\Modules\Shared\Events\OrderPlaced;
use App\Modules\Vendors\Infrastructure\Models\Vendor;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function agent(Role $role = Role::Support): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

function placeOrder(int $quantity = 1, string $phone = '0700000001')
{
    $offer = Offer::factory()->create(['price_amount' => 10_000, 'stock_quantity' => 3]);

    return test()->postJson('/api/v1/orders', [
        'items' => [['offer_id' => $offer->id, 'quantity' => $quantity]],
        'customer_name' => 'Awa Diallo', 'customer_phone' => $phone, 'delivery_address' => 'Cocody',
    ]);
}

it('texts the customer when an order is placed, with the amount and delivery code', function () {
    $response = placeOrder()->assertCreated();

    $notification = Notification::query()->sole();

    expect($notification->event)->toBe('order.placed')
        ->and($notification->channel)->toBe('sms')
        ->and($notification->recipient)->toBe('0700000001')
        ->and($notification->reference)->toBe($response->json('order_number'))
        ->and($notification->status)->toBe(NotificationStatus::Sent)
        ->and($notification->attempts)->toBe(1)
        ->and($notification->body)->toContain($response->json('delivery_code'))
        ->and($notification->body)->toContain('10 000 F CFA');
});

it('sends nothing when the order fails and rolls back', function () {
    placeOrder(quantity: 99)->assertUnprocessable();

    expect(Notification::query()->count())->toBe(0);
});

it('tells the customer at each real status change, once', function () {
    $orderNumber = placeOrder()->json('order_number');
    $staff = agent(Role::OperateurCommandes);

    foreach (['confirmed', 'shipped', 'delivered'] as $status) {
        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/v1/orders/{$orderNumber}/transition", ['status' => $status])->assertOk();
    }

    expect(Notification::query()->orderBy('id')->pluck('event')->all())
        ->toBe(['order.placed', 'order.confirmed', 'order.shipped', 'order.delivered']);
});

it('stays silent while one parcel moves without changing the order status', function () {
    $a = Vendor::factory()->active()->create();
    $b = Vendor::factory()->active()->create();
    $order = Order::factory()->create(['status' => OrderStatus::Confirmed, 'customer_phone' => '0700000005']);
    foreach ([$a->id, $b->id] as $vendorId) {
        SubOrder::factory()->create(['order_id' => $order->id, 'vendor_id' => $vendorId, 'status' => OrderStatus::Confirmed]);
    }
    $staff = agent(Role::OperateurCommandes);
    $subs = $order->subOrders;

    // First parcel ships: parent stays "confirmed" (the other is not shipped) -> no message.
    $this->actingAs($staff, 'sanctum')
        ->postJson("/api/v1/orders/{$order->order_number}/sub-orders/{$subs[0]->id}/transition", ['status' => 'shipped'])->assertOk();
    expect(Notification::query()->count())->toBe(0);

    // Second parcel ships: the order is now shipped -> one message.
    $this->actingAs($staff, 'sanctum')
        ->postJson("/api/v1/orders/{$order->order_number}/sub-orders/{$subs[1]->id}/transition", ['status' => 'shipped'])->assertOk();
    expect(Notification::query()->sole()->event)->toBe('order.shipped');
});

it('does not text twice when an event is replayed', function () {
    $event = new OrderPlaced('TCH-REPLAY', 'Awa', '0700000002', 5_000, 'XOF', '111111');

    event($event);
    event($event);

    expect(Notification::query()->where('reference', 'TCH-REPLAY')->count())->toBe(1);
});

it('texts nothing when there is no phone number', function () {
    event(new OrderPlaced('TCH-NOPHONE', 'Awa', '   ', 5_000, 'XOF', null));

    expect(Notification::query()->count())->toBe(0);
});

it('records one message per enabled channel', function () {
    config(['notifications.channels' => ['sms', 'whatsapp']]);

    event(new OrderPlaced('TCH-TWO', 'Awa', '0700000003', 5_000, 'XOF', null));

    expect(Notification::query()->orderBy('id')->pluck('channel')->all())->toBe(['sms', 'whatsapp']);
});

it('never lets a delivery failure break the order, and keeps the failure for a retry', function () {
    config(['notifications.drivers.sms' => 'unknown-provider']);

    $response = placeOrder()->assertCreated(); // the order is placed regardless

    $notification = Notification::query()->sole();
    expect($notification->status)->toBe(NotificationStatus::Failed)
        ->and($notification->error)->toContain('unknown-provider')
        ->and($response->json('order_number'))->toBe($notification->reference);

    // The provider is fixed: retry from the back office.
    config(['notifications.drivers.sms' => 'log']);
    $this->actingAs(agent(), 'sanctum')
        ->postJson("/api/v1/admin/notifications/{$notification->id}/retry")
        ->assertOk()
        ->assertJsonPath('status', 'sent')
        ->assertJsonPath('attempts', 2);
});

it('texts the customer when a return is refunded', function () {
    $order = Order::factory()->create(['customer_phone' => '0700000007']);
    $return = ReturnRequest::factory()->status(ReturnStatus::Received)->create([
        'order_number' => $order->order_number, 'requested_amount' => 30_000,
    ]);

    $this->actingAs(agent(Role::Administrateur), 'sanctum')
        ->postJson("/api/v1/admin/returns/{$return->id}/refund")->assertOk();

    $notification = Notification::query()->where('event', 'return.refunded')->sole();
    expect($notification->recipient)->toBe('0700000007')
        ->and($notification->body)->toContain('30 000 F CFA');
});

it('restricts the journal to support and administrators', function () {
    $this->getJson('/api/v1/admin/notifications')->assertUnauthorized();

    foreach ([Role::Marketing, Role::Comptable, Role::VendeurTiers, Role::Client] as $role) {
        $this->actingAs(agent($role), 'sanctum')->getJson('/api/v1/admin/notifications')->assertForbidden();
    }

    $this->actingAs(agent(Role::Administrateur), 'sanctum')->getJson('/api/v1/admin/notifications')->assertOk();
});

it('filters the journal by status and order', function () {
    $sent = Notification::factory()->create();
    Notification::factory()->failed()->create();
    $user = agent();

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/notifications?status=failed')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'failed');

    $this->actingAs($user, 'sanctum')->getJson("/api/v1/admin/notifications?reference={$sent->reference}")
        ->assertOk()->assertJsonCount(1, 'data');
});

it('only retries a failed notification', function () {
    $sent = Notification::factory()->create();

    $this->actingAs(agent(), 'sanctum')
        ->postJson("/api/v1/admin/notifications/{$sent->id}/retry")
        ->assertUnprocessable();
});
