<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use App\Modules\Payouts\Infrastructure\Models\Payout;
use App\Modules\Vendors\Infrastructure\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function payoutUser(Role $role = Role::Comptable): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

/** A delivered order with one vendor line (and optionally a 1P line). */
function deliveredOrder(int $vendorId, int $lineTotal, int $daysAgo = 10, bool $withOwnLine = false): Order
{
    $order = Order::factory()->create([
        'status' => OrderStatus::Delivered,
        'delivered_at' => now()->subDays($daysAgo),
        'subtotal_amount' => $lineTotal,
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'vendor_id' => $vendorId,
        'unit_price_amount' => $lineTotal,
        'quantity' => 1,
        'line_total_amount' => $lineTotal,
    ]);

    if ($withOwnLine) {
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'vendor_id' => null,
            'unit_price_amount' => 7_000,
            'quantity' => 1,
            'line_total_amount' => 7_000,
        ]);
    }

    return $order;
}

it('restricts payouts to finance roles', function () {
    $this->getJson('/api/v1/admin/payouts')->assertUnauthorized();
    $this->actingAs(payoutUser(Role::Support), 'sanctum')->getJson('/api/v1/admin/payouts')->assertForbidden();
});

it('calculates a vendor payout net of commission, ignoring 1P lines', function () {
    $vendor = Vendor::factory()->active()->create();
    deliveredOrder($vendor->id, 100_000, withOwnLine: true);
    deliveredOrder($vendor->id, 50_000);

    $response = $this->actingAs(payoutUser(), 'sanctum')
        ->postJson('/api/v1/admin/payouts', ['vendor_id' => $vendor->id]);

    $response->assertCreated()
        ->assertJsonPath('status', 'pending')
        ->assertJsonPath('vendor_name', $vendor->company_name)
        ->assertJsonPath('gross_amount', 150_000)
        ->assertJsonPath('commission_amount', 15_000)
        ->assertJsonPath('net_amount', 135_000)
        ->assertJsonCount(2, 'items');
});

it('only includes the requested vendor and orders past the return window', function () {
    $vendor = Vendor::factory()->active()->create();
    $other = Vendor::factory()->active()->create();
    deliveredOrder($vendor->id, 40_000);
    deliveredOrder($vendor->id, 99_000, daysAgo: 2); // still inside the return window
    deliveredOrder($other->id, 77_000);

    $this->actingAs(payoutUser(), 'sanctum')
        ->postJson('/api/v1/admin/payouts', ['vendor_id' => $vendor->id])
        ->assertCreated()
        ->assertJsonPath('gross_amount', 40_000);
});

it('does not count undelivered orders', function () {
    $vendor = Vendor::factory()->active()->create();
    $order = deliveredOrder($vendor->id, 40_000);
    $order->update(['status' => OrderStatus::Shipped]);

    $this->actingAs(payoutUser(), 'sanctum')
        ->postJson('/api/v1/admin/payouts', ['vendor_id' => $vendor->id])
        ->assertUnprocessable();
});

it('never pays the same order twice', function () {
    $vendor = Vendor::factory()->active()->create();
    deliveredOrder($vendor->id, 40_000);
    $user = payoutUser();

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/payouts', ['vendor_id' => $vendor->id])->assertCreated();
    $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/payouts', ['vendor_id' => $vendor->id])->assertUnprocessable();

    expect(Payout::query()->count())->toBe(1);
});

it('rejects an unknown vendor', function () {
    $this->actingAs(payoutUser(), 'sanctum')
        ->postJson('/api/v1/admin/payouts', ['vendor_id' => 99999])
        ->assertUnprocessable();
});

it('marks a payout paid once, with a reference', function () {
    $payout = Payout::factory()->create();
    $user = payoutUser();

    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/payouts/{$payout->id}/pay", [])->assertUnprocessable();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/admin/payouts/{$payout->id}/pay", ['payment_reference' => 'WAVE-123'])
        ->assertOk()
        ->assertJsonPath('status', 'paid')
        ->assertJsonPath('payment_reference', 'WAVE-123');

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/admin/payouts/{$payout->id}/pay", ['payment_reference' => 'WAVE-123'])
        ->assertUnprocessable();
});

it('books commission and vendor debt, then settles it, keeping the ledger balanced', function () {
    $vendor = Vendor::factory()->active()->create();
    deliveredOrder($vendor->id, 100_000);
    $user = payoutUser();

    $payout = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/admin/payouts', ['vendor_id' => $vendor->id])->json();

    $balance = fn () => collect(
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/accounting/trial-balance')->json('accounts'),
    )->keyBy('code');

    expect($balance()['706']['credit'])->toBe(10_000)
        ->and($balance()['4011']['credit'])->toBe(90_000)
        ->and($balance()['701']['debit'])->toBe(100_000);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/admin/payouts/{$payout['id']}/pay", ['payment_reference' => 'REF'])->assertOk();

    expect($balance()['4011']['balance'])->toBe(0);

    $totals = $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/accounting/trial-balance')->json();
    expect($totals['total_debit'])->toBe($totals['total_credit']);
});

it('lists payouts filtered by status and shows a statement', function () {
    $paid = Payout::factory()->create(['status' => 'paid']);
    Payout::factory()->create();
    $user = payoutUser();

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/payouts?status=paid')
        ->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($user, 'sanctum')->getJson("/api/v1/admin/payouts/{$paid->id}")
        ->assertOk()->assertJsonPath('id', $paid->id);
});

it('stamps the delivery date when an order is delivered', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Shipped]);

    (new App\Modules\Orders\Application\Actions\TransitionOrderStatusAction)->execute($order, OrderStatus::Delivered);

    expect($order->fresh()->delivered_at)->not->toBeNull();
    expect(DB::getSchemaBuilder()->hasColumn('order_items', 'vendor_id'))->toBeTrue();
});
