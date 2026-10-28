<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use App\Modules\Returns\Domain\ReturnStatus;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function returnShopper(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Client->value);

    return $user;
}

/** A delivered order of the customer: two units of one offer at 59 000 each. */
function deliveredFor(?User $customer, int $daysAgo = 1): array
{
    $order = Order::factory()->create([
        'status' => OrderStatus::Delivered,
        'delivered_at' => now()->subDays($daysAgo),
        'subtotal_amount' => 118_000,
        'customer_id' => $customer?->id,
    ]);
    $item = OrderItem::factory()->create([
        'order_id' => $order->id, 'unit_price_amount' => 59_000, 'quantity' => 2, 'line_total_amount' => 118_000,
    ]);

    return [$order, $item];
}

function askReturn(User $by, Order $order, array $items, string $reason = 'Écran défectueux')
{
    return test()->actingAs($by, 'sanctum')->postJson('/api/v1/customer/returns', [
        'order_number' => $order->order_number, 'reason' => $reason, 'items' => $items,
    ]);
}

it('requires authentication', function () {
    [$order] = deliveredFor(returnShopper());

    $this->postJson('/api/v1/customer/returns', [])->assertUnauthorized();
    $this->getJson("/api/v1/customer/orders/{$order->order_number}/returns")->assertUnauthorized();
});

it('lets a customer return articles of their delivered order, the amount following the articles', function () {
    $customer = returnShopper();
    [$order, $item] = deliveredFor($customer);

    askReturn($customer, $order, [['offer_id' => $item->offer_id, 'quantity' => 1]])
        ->assertCreated()
        ->assertJsonPath('status', 'requested')
        ->assertJsonPath('requested_amount', 59_000)
        ->assertJsonPath('items.0.quantity', 1);

    $return = ReturnRequest::query()->sole();
    expect($return->created_by)->toBe($customer->id)->and($return->status)->toBe(ReturnStatus::Requested);
});

it('ignores any amount the customer tries to send', function () {
    $customer = returnShopper();
    [$order, $item] = deliveredFor($customer);

    $this->actingAs($customer, 'sanctum')->postJson('/api/v1/customer/returns', [
        'order_number' => $order->order_number, 'reason' => 'Écran défectueux', 'amount' => 118_000,
        'items' => [['offer_id' => $item->offer_id, 'quantity' => 1]],
    ])->assertCreated()->assertJsonPath('requested_amount', 59_000);
});

it('requires named articles and a real reason', function () {
    $customer = returnShopper();
    [$order, $item] = deliveredFor($customer);

    $this->actingAs($customer, 'sanctum')->postJson('/api/v1/customer/returns', [
        'order_number' => $order->order_number, 'reason' => 'Écran défectueux',
    ])->assertUnprocessable()->assertJsonValidationErrors('items');

    askReturn($customer, $order, [['offer_id' => $item->offer_id, 'quantity' => 1]], 'x')
        ->assertUnprocessable()->assertJsonValidationErrors('reason');
});

it('refuses more than was bought, and what earlier returns already claim', function () {
    $customer = returnShopper();
    [$order, $item] = deliveredFor($customer);

    askReturn($customer, $order, [['offer_id' => $item->offer_id, 'quantity' => 3]])->assertUnprocessable();
    askReturn($customer, $order, [['offer_id' => $item->offer_id, 'quantity' => 2]])->assertCreated();
    askReturn($customer, $order, [['offer_id' => $item->offer_id, 'quantity' => 1]])->assertUnprocessable();
});

it('refuses an order not delivered yet, or past the return window', function () {
    $customer = returnShopper();
    $pending = Order::factory()->create(['status' => OrderStatus::Confirmed, 'customer_id' => $customer->id]);
    $item = OrderItem::factory()->create(['order_id' => $pending->id, 'quantity' => 1]);
    askReturn($customer, $pending, [['offer_id' => $item->offer_id, 'quantity' => 1]])->assertUnprocessable();

    [$old, $oldItem] = deliveredFor($customer, daysAgo: 400);
    askReturn($customer, $old, [['offer_id' => $oldItem->offer_id, 'quantity' => 1]])->assertUnprocessable();
});

it('answers 404, never 403, for another customer\'s order, a guest order or an unknown number', function () {
    [$order, $item] = deliveredFor(returnShopper());
    [$guest, $guestItem] = deliveredFor(null);
    $intruder = returnShopper();

    askReturn($intruder, $order, [['offer_id' => $item->offer_id, 'quantity' => 1]])->assertNotFound();
    askReturn($intruder, $guest, [['offer_id' => $guestItem->offer_id, 'quantity' => 1]])->assertNotFound();
    $this->actingAs($intruder, 'sanctum')->postJson('/api/v1/customer/returns', [
        'order_number' => 'TCH-NOPE', 'reason' => 'Écran défectueux', 'items' => [['offer_id' => 1, 'quantity' => 1]],
    ])->assertNotFound();

    $this->actingAs($intruder, 'sanctum')->getJson("/api/v1/customer/orders/{$order->order_number}/returns")->assertNotFound();
    expect(ReturnRequest::query()->count())->toBe(0);
});

it('lists the returns of the customer\'s own order with their progress', function () {
    $customer = returnShopper();
    [$order, $item] = deliveredFor($customer);
    askReturn($customer, $order, [['offer_id' => $item->offer_id, 'quantity' => 1]])->assertCreated();

    $staff = User::factory()->create();
    $staff->assignRole(Role::Support->value);
    $id = ReturnRequest::query()->sole()->id;
    $this->actingAs($staff, 'sanctum')->postJson("/api/v1/admin/returns/{$id}/accept", ['note' => 'Renvoyez le colis'])->assertOk();

    $this->actingAs($customer, 'sanctum')->getJson("/api/v1/customer/orders/{$order->order_number}/returns")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'accepted')
        ->assertJsonPath('data.0.decision_note', 'Renvoyez le colis')
        ->assertJsonMissingPath('data.0.created_by');
});

it('does not list other customers\' returns on the same endpoint', function () {
    $customer = returnShopper();
    [$order, $item] = deliveredFor($customer);
    [$other, $otherItem] = deliveredFor(returnShopper());
    askReturn($customer, $order, [['offer_id' => $item->offer_id, 'quantity' => 1]])->assertCreated();

    $this->actingAs($customer, 'sanctum')->getJson("/api/v1/customer/orders/{$order->order_number}/returns")->assertJsonCount(1, 'data');
    expect(ReturnRequest::query()->where('order_number', $other->order_number)->count())->toBe(0);
});
