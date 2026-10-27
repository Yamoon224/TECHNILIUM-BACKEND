<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use App\Modules\Payouts\Infrastructure\Models\Payout;
use App\Modules\Returns\Domain\ReturnStatus;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use App\Modules\Vendors\Infrastructure\Models\Vendor;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function supportUser(Role $role = Role::Support): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

function returnableOrder(int $total = 118_000, int $daysAgo = 1, ?int $vendorId = null): Order
{
    $order = Order::factory()->create([
        'status' => OrderStatus::Delivered,
        'delivered_at' => now()->subDays($daysAgo),
        'subtotal_amount' => $total,
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'vendor_id' => $vendorId,
        'unit_price_amount' => $total,
        'quantity' => 1,
        'line_total_amount' => $total,
    ]);

    return $order;
}

it('restricts returns to support and administrators', function () {
    $this->getJson('/api/v1/admin/returns')->assertUnauthorized();
    $this->actingAs(supportUser(Role::Marketing), 'sanctum')->getJson('/api/v1/admin/returns')->assertForbidden();
});

it('opens a return on a delivered order, defaulting to the whole refundable amount', function () {
    $order = returnableOrder();

    $this->actingAs(supportUser(), 'sanctum')
        ->postJson('/api/v1/admin/returns', ['order_number' => $order->order_number, 'reason' => 'Défectueux'])
        ->assertCreated()
        ->assertJsonPath('status', 'requested')
        ->assertJsonPath('requested_amount', 118_000)
        ->assertJsonPath('number', fn (string $n) => str_starts_with($n, 'RMA-'));
});

it('refuses a return on an unknown, undelivered or out-of-window order', function () {
    $user = supportUser();
    $shipped = Order::factory()->create(['status' => OrderStatus::Shipped]);
    $old = returnableOrder(daysAgo: 30);

    foreach (['TCH-NOPE', $shipped->order_number, $old->order_number] as $number) {
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/admin/returns', ['order_number' => $number, 'reason' => 'x'])
            ->assertUnprocessable();
    }
});

it('never lets open and refunded returns exceed the order total', function () {
    $order = returnableOrder(100_000);
    $user = supportUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/admin/returns', ['order_number' => $order->order_number, 'reason' => 'a', 'amount' => 60_000])
        ->assertCreated();
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/admin/returns', ['order_number' => $order->order_number, 'reason' => 'b', 'amount' => 50_000])
        ->assertUnprocessable();
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/admin/returns', ['order_number' => $order->order_number, 'reason' => 'c', 'amount' => 40_000])
        ->assertCreated();
});

it('walks the lifecycle and refuses illegal transitions', function () {
    $return = ReturnRequest::factory()->create();
    $user = supportUser();

    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/returns/{$return->id}/refund")->assertUnprocessable();
    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/returns/{$return->id}/receive")->assertUnprocessable();
    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/returns/{$return->id}/accept")
        ->assertOk()->assertJsonPath('status', 'accepted');
    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/returns/{$return->id}/receive")
        ->assertOk()->assertJsonPath('status', 'received');
    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/returns/{$return->id}/refund")
        ->assertOk()->assertJsonPath('status', 'refunded')->assertJsonPath('refunded_amount', 10_000);
    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/returns/{$return->id}/reject")->assertUnprocessable();
});

it('keeps the decision note when a return is rejected', function () {
    $return = ReturnRequest::factory()->create();

    $this->actingAs(supportUser(), 'sanctum')
        ->postJson("/api/v1/admin/returns/{$return->id}/reject", ['note' => 'Hors garantie'])
        ->assertOk()
        ->assertJsonPath('decision_note', 'Hors garantie');
});

it('refuses to refund more than requested but accepts a lower amount', function () {
    $return = ReturnRequest::factory()->status(ReturnStatus::Received)->create(['requested_amount' => 10_000]);
    $user = supportUser();

    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/returns/{$return->id}/refund", ['amount' => 10_001])->assertUnprocessable();
    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/returns/{$return->id}/refund", ['amount' => 4_000])
        ->assertOk()->assertJsonPath('refunded_amount', 4_000);
});

it('credits the live invoice with a partial credit note when a return is refunded', function () {
    $order = returnableOrder(118_000);
    $user = supportUser(Role::Administrateur);
    $invoice = Invoice::factory()->create([
        'order_number' => $order->order_number,
        'total_amount' => 118_000, 'net_amount' => 100_000, 'vat_amount' => 18_000,
    ]);

    $return = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/admin/returns', ['order_number' => $order->order_number, 'reason' => 'Écran cassé', 'amount' => 59_000])->json();
    foreach (['accept', 'receive', 'refund'] as $step) {
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/returns/{$return['id']}/{$step}")->assertOk();
    }

    $credit = Invoice::query()->where('credited_invoice_id', $invoice->id)->sole();
    expect($credit->total_amount)->toBe(59_000)
        ->and($credit->net_amount + $credit->vat_amount)->toBe(59_000)
        ->and($credit->reason)->toContain('Écran cassé')
        ->and($invoice->fresh()->remainingCreditable())->toBe(59_000);
});

it('refunds without a credit note when the order was never invoiced', function () {
    $return = ReturnRequest::factory()->status(ReturnStatus::Received)->create();

    $this->actingAs(supportUser(), 'sanctum')->postJson("/api/v1/admin/returns/{$return->id}/refund")->assertOk();

    expect(Invoice::query()->count())->toBe(0);
});

it('holds a vendor payout while a return is open, then deducts the refund once decided', function () {
    $vendor = Vendor::factory()->active()->create();
    $order = returnableOrder(100_000, daysAgo: 10, vendorId: $vendor->id);
    $return = ReturnRequest::factory()->create([
        'order_number' => $order->order_number, 'requested_amount' => 30_000,
    ]);
    $finance = supportUser(Role::Administrateur);

    // Open return → nothing payable yet.
    $this->actingAs($finance, 'sanctum')->postJson('/api/v1/admin/payouts', ['vendor_id' => $vendor->id])->assertUnprocessable();

    foreach (['accept', 'receive', 'refund'] as $step) {
        $this->actingAs($finance, 'sanctum')->postJson("/api/v1/admin/returns/{$return->id}/{$step}")->assertOk();
    }

    // 30 000 refunded → vendor keeps 70 000 gross, commission 10 % = 7 000.
    $this->actingAs($finance, 'sanctum')
        ->postJson('/api/v1/admin/payouts', ['vendor_id' => $vendor->id])
        ->assertCreated()
        ->assertJsonPath('gross_amount', 70_000)
        ->assertJsonPath('commission_amount', 7_000)
        ->assertJsonPath('net_amount', 63_000)
        ->assertJsonPath('items.0.refund_deduction_amount', 30_000);

    expect(Payout::query()->count())->toBe(1);
});

it('lists returns filtered by status', function () {
    ReturnRequest::factory()->create();
    ReturnRequest::factory()->status(ReturnStatus::Rejected)->create();

    $this->actingAs(supportUser(), 'sanctum')
        ->getJson('/api/v1/admin/returns?status=rejected')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
