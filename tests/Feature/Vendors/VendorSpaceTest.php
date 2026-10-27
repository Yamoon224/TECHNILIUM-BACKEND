<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use App\Modules\Payouts\Domain\PayoutStatus;
use App\Modules\Payouts\Infrastructure\Models\Payout;
use App\Modules\Payouts\Infrastructure\Models\PayoutItem;
use App\Modules\Vendors\Infrastructure\Models\Vendor;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

/** @return array{User, Vendor} */
function vendorUser(?string $status = 'active'): array
{
    $user = User::factory()->create();
    $user->assignRole(Role::VendeurTiers->value);
    $vendor = Vendor::factory()->create(['user_id' => $user->id, 'status' => $status]);

    return [$user, $vendor];
}

function orderWithLines(array $lines, OrderStatus $status = OrderStatus::Confirmed): Order
{
    $order = Order::factory()->create([
        'status' => $status,
        'subtotal_amount' => array_sum(array_column($lines, 1)),
    ]);

    foreach ($lines as [$vendorId, $total]) {
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'vendor_id' => $vendorId,
            'unit_price_amount' => $total,
            'quantity' => 1,
            'line_total_amount' => $total,
        ]);
    }

    return $order;
}

dataset('vendor endpoints', ['/summary', '/orders', '/payouts']);

it('rejects anonymous callers and non-vendor roles', function (string $path) {
    $this->getJson("/api/v1/vendors/me{$path}")->assertUnauthorized();

    $staff = User::factory()->create();
    $staff->assignRole(Role::Comptable->value);
    $this->actingAs($staff, 'sanctum')->getJson("/api/v1/vendors/me{$path}")->assertForbidden();
})->with('vendor endpoints');

it('rejects a vendor whose profile is not active', function (string $path) {
    [$user] = vendorUser('pending');

    $this->actingAs($user, 'sanctum')->getJson("/api/v1/vendors/me{$path}")->assertForbidden();
})->with('vendor endpoints');

it('shows only the vendor\'s own lines and share of a shared order', function () {
    [$user, $vendor] = vendorUser();
    [, $other] = vendorUser();
    $order = orderWithLines([[$vendor->id, 40_000], [$other->id, 99_000], [null, 7_000]]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/vendors/me/orders')->assertOk();

    $response->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.order_number', $order->order_number)
        ->assertJsonPath('data.0.vendor_subtotal_amount', 40_000)
        ->assertJsonCount(1, 'data.0.lines')
        ->assertJsonPath('data.0.lines.0.line_total_amount', 40_000);

    // Nothing of the other vendor's or Technilium's lines leaks into the payload.
    expect(json_encode($response->json()))->not->toContain('99000')->not->toContain('7000');
});

it('never lists an order that has none of the vendor\'s lines', function () {
    [$user] = vendorUser();
    [, $other] = vendorUser();
    orderWithLines([[$other->id, 50_000]]);

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/vendors/me/orders')
        ->assertOk()->assertJsonCount(0, 'data');
});

it('lists and details only the vendor\'s own payouts, 404 for another vendor\'s', function () {
    [$user, $vendor] = vendorUser();
    [, $other] = vendorUser();
    $mine = Payout::factory()->create(['vendor_id' => $vendor->id]);
    PayoutItem::query()->create([
        'payout_id' => $mine->id, 'vendor_id' => $vendor->id, 'order_number' => 'TCH-1',
        'gross_amount' => 100_000, 'commission_amount' => 10_000, 'refund_deduction_amount' => 0,
    ]);
    $theirs = Payout::factory()->create(['vendor_id' => $other->id]);

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/vendors/me/payouts')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id);

    $this->actingAs($user, 'sanctum')->getJson("/api/v1/vendors/me/payouts/{$mine->id}")
        ->assertOk()
        ->assertJsonPath('net_amount', 90_000)
        ->assertJsonPath('items.0.order_number', 'TCH-1');

    // Same response as a payout that does not exist: no oracle on other vendors' ids.
    $this->actingAs($user, 'sanctum')->getJson("/api/v1/vendors/me/payouts/{$theirs->id}")->assertNotFound();
    $this->actingAs($user, 'sanctum')->getJson('/api/v1/vendors/me/payouts/999999')->assertNotFound();
});

it('ignores any vendor_id passed by the caller', function () {
    [$user] = vendorUser();
    [, $other] = vendorUser();
    Payout::factory()->create(['vendor_id' => $other->id]);

    $this->actingAs($user, 'sanctum')->getJson("/api/v1/vendors/me/payouts?vendor_id={$other->id}")
        ->assertOk()->assertJsonCount(0, 'data');
    $this->actingAs($user, 'sanctum')->getJson("/api/v1/vendors/me/orders?vendor_id={$other->id}")
        ->assertOk()->assertJsonCount(0, 'data');
});

it('summarises orders, payouts and offers for the vendor only', function () {
    [$user, $vendor] = vendorUser();
    [, $other] = vendorUser();
    orderWithLines([[$vendor->id, 10_000]]);
    orderWithLines([[$vendor->id, 20_000]], OrderStatus::Delivered);
    orderWithLines([[$other->id, 30_000]]);
    Payout::factory()->create(['vendor_id' => $vendor->id, 'net_amount' => 90_000]);
    Payout::factory()->create(['vendor_id' => $vendor->id, 'net_amount' => 45_000, 'status' => PayoutStatus::Paid]);
    Payout::factory()->create(['vendor_id' => $other->id, 'net_amount' => 777_000]);

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/vendors/me/summary')
        ->assertOk()
        ->assertJsonPath('company_name', $vendor->company_name)
        ->assertJsonPath('orders_by_status.confirmed', 1)
        ->assertJsonPath('orders_by_status.delivered', 1)
        ->assertJsonPath('payout_pending_amount', 90_000)
        ->assertJsonPath('payout_paid_amount', 45_000);
});
