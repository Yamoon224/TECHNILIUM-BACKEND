<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Inventory\Infrastructure\Models\StockMovement;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use App\Modules\Payments\Infrastructure\Models\Payment;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function boss(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Administrateur->value);

    return $user;
}

/** A delivered order of 2 × offerA (30 000) + 1 × offerB (40 000) = 100 000. */
function deliveredSale(): array
{
    $a = Offer::factory()->create(['price_amount' => 30_000, 'stock_quantity' => 10]);
    $b = Offer::factory()->create(['price_amount' => 40_000, 'stock_quantity' => 10]);
    $order = Order::factory()->create([
        'status' => OrderStatus::Delivered, 'delivered_at' => now()->subDay(), 'subtotal_amount' => 100_000,
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id, 'offer_id' => $a->id, 'unit_price_amount' => 30_000, 'quantity' => 2, 'line_total_amount' => 60_000,
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id, 'offer_id' => $b->id, 'unit_price_amount' => 40_000, 'quantity' => 1, 'line_total_amount' => 40_000,
    ]);

    return [$order, $a, $b];
}

it('prices a return from its articles and records them', function () {
    [$order, $a] = deliveredSale();

    $this->actingAs(boss(), 'sanctum')
        ->postJson('/api/v1/admin/returns', [
            'order_number' => $order->order_number, 'reason' => 'Écran rayé',
            'items' => [['offer_id' => $a->id, 'quantity' => 1]],
        ])
        ->assertCreated()
        ->assertJsonPath('requested_amount', 30_000)
        ->assertJsonPath('items.0.offer_id', $a->id);
});

it('refuses articles the order never sold or in too large a quantity', function () {
    [$order, $a] = deliveredSale();
    $user = boss();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/admin/returns', [
            'order_number' => $order->order_number, 'reason' => 'x',
            'items' => [['offer_id' => $a->id, 'quantity' => 3]],
        ])->assertUnprocessable();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/admin/returns', [
            'order_number' => $order->order_number, 'reason' => 'x',
            'items' => [['offer_id' => 999_999, 'quantity' => 1]],
        ])->assertUnprocessable();
});

it('does not let two returns claim the same article twice, but frees it once rejected', function () {
    [$order, , $b] = deliveredSale();
    $user = boss();
    $payload = ['order_number' => $order->order_number, 'reason' => 'x', 'items' => [['offer_id' => $b->id, 'quantity' => 1]]];

    $first = $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/returns', $payload)->assertCreated()->json();
    $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/returns', $payload)->assertUnprocessable();

    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/returns/{$first['id']}/reject")->assertOk();
    $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/returns', $payload)->assertCreated();
});

it('puts resellable articles back in stock on receipt, only when asked', function () {
    [$order, $a] = deliveredSale();
    $user = boss();

    $open = fn () => $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/returns', [
        'order_number' => $order->order_number, 'reason' => 'x',
        'items' => [['offer_id' => $a->id, 'quantity' => 2]],
    ])->json('id');

    // Damaged: received, NOT restocked.
    $damaged = $open();
    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/returns/{$damaged}/accept")->assertOk();
    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/returns/{$damaged}/receive", ['restock' => false])
        ->assertOk()->assertJsonPath('restocked', false);
    expect($a->fresh()->stock_quantity)->toBe(10)->and(StockMovement::query()->count())->toBe(0);
    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/returns/{$damaged}/reject")->assertOk();

    // Resellable: restocked once, with an audit movement.
    $good = $open();
    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/returns/{$good}/accept")->assertOk();
    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/returns/{$good}/receive", ['restock' => true])
        ->assertOk()->assertJsonPath('restocked', true);

    expect($a->fresh()->stock_quantity)->toBe(12);
    $movement = StockMovement::query()->sole();
    expect($movement->quantity_delta)->toBe(2)->and($movement->reason)->toContain('RMA-');
});

it('does not restock a return that names no articles', function () {
    [$order] = deliveredSale();
    $user = boss();
    $id = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/admin/returns', ['order_number' => $order->order_number, 'reason' => 'geste', 'amount' => 5_000])
        ->json('id');

    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/returns/{$id}/accept")->assertOk();
    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/returns/{$id}/receive", ['restock' => true])
        ->assertOk()->assertJsonPath('restocked', false);

    expect(StockMovement::query()->count())->toBe(0);
});

it('refunds the customer on the payment when a return is refunded, and keeps the ledger balanced', function () {
    [$order, $a] = deliveredSale();
    $payment = Payment::factory()->completed()->create([
        'order_number' => $order->order_number, 'amount_amount' => 100_000, 'method' => 'mobile_money', 'provider' => 'wave',
    ]);
    Invoice::factory()->create([
        'order_number' => $order->order_number, 'total_amount' => 100_000, 'net_amount' => 84_746, 'vat_amount' => 15_254,
    ]);
    $user = boss();

    $id = $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/returns', [
        'order_number' => $order->order_number, 'reason' => 'Défaut',
        'items' => [['offer_id' => $a->id, 'quantity' => 1]],
    ])->json('id');
    foreach (['accept', 'receive', 'refund'] as $step) {
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/returns/{$id}/{$step}")->assertOk();
    }

    // Partial refund recorded: the payment stays completed with 70 000 left.
    $payment->refresh();
    expect($payment->refunded_amount)->toBe(30_000)
        ->and($payment->refundable())->toBe(70_000)
        ->and($payment->status->value)->toBe('completed');

    $balance = $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/accounting/trial-balance')->json();
    expect($balance['total_debit'])->toBe($balance['total_credit']);
});

it('leaves an unpaid order without a payment refund', function () {
    [$order] = deliveredSale();
    $payment = Payment::factory()->create(['order_number' => $order->order_number, 'amount_amount' => 100_000]); // pending
    $user = boss();

    $id = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/admin/returns', ['order_number' => $order->order_number, 'reason' => 'x', 'amount' => 10_000])->json('id');
    foreach (['accept', 'receive', 'refund'] as $step) {
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/returns/{$id}/{$step}")->assertOk();
    }

    expect($payment->fresh()->refunded_amount)->toBe(0);
});
