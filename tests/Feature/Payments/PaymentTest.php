<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Payments\Infrastructure\Models\Payment;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function comptable(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Comptable->value);

    return $user;
}

it('rejects an anonymous request to list payments', function () {
    $this->getJson('/api/v1/admin/payments')->assertUnauthorized();
});

it('rejects roles other than comptable from managing payments', function (Role $role) {
    $user = User::factory()->create();
    $user->assignRole($role->value);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/admin/payments')
        ->assertForbidden();
})->with([
    Role::OperateurCommandes,
    Role::GestionnaireCatalogue,
    Role::VendeurTiers,
    Role::Client,
]);

it('records a pending cash-on-delivery payment for an existing order', function () {
    $order = Order::factory()->create();

    $response = $this->actingAs(comptable(), 'sanctum')
        ->postJson('/api/v1/admin/payments', [
            'order_number' => $order->order_number,
            'method' => 'cod',
            'amount_amount' => $order->subtotal_amount,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('status', 'pending');
    $response->assertJsonPath('method', 'cod');
});

it('requires a provider when recording a mobile money payment', function () {
    $order = Order::factory()->create();

    $this->actingAs(comptable(), 'sanctum')
        ->postJson('/api/v1/admin/payments', [
            'order_number' => $order->order_number,
            'method' => 'mobile_money',
            'amount_amount' => $order->subtotal_amount,
        ])
        ->assertUnprocessable();
});

it('records a mobile money payment with its provider reference', function () {
    $order = Order::factory()->create();

    $response = $this->actingAs(comptable(), 'sanctum')
        ->postJson('/api/v1/admin/payments', [
            'order_number' => $order->order_number,
            'method' => 'mobile_money',
            'amount_amount' => $order->subtotal_amount,
            'provider' => 'orange_money',
            'provider_reference' => 'MM-12345678',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('provider', 'orange_money');
    $response->assertJsonPath('provider_reference', 'MM-12345678');
});

it('rejects a payment for an unknown order', function () {
    $this->actingAs(comptable(), 'sanctum')
        ->postJson('/api/v1/admin/payments', [
            'order_number' => 'TCH-DOES-NOT-EXIST',
            'method' => 'cod',
            'amount_amount' => 1000,
        ])
        ->assertUnprocessable();
});

it('confirms a pending payment', function () {
    $payment = Payment::factory()->create();

    $response = $this->actingAs(comptable(), 'sanctum')
        ->postJson("/api/v1/admin/payments/{$payment->id}/confirm");

    $response->assertOk();
    $response->assertJsonPath('status', 'completed');
    expect($payment->fresh()->paid_at)->not->toBeNull();
});

it('fails a pending payment', function () {
    $payment = Payment::factory()->create();

    $this->actingAs(comptable(), 'sanctum')
        ->postJson("/api/v1/admin/payments/{$payment->id}/fail")
        ->assertOk()
        ->assertJsonPath('status', 'failed');
});

it('allows retrying a failed payment', function () {
    $payment = Payment::factory()->failed()->create();

    $this->actingAs(comptable(), 'sanctum')
        ->postJson("/api/v1/admin/payments/{$payment->id}/confirm")
        ->assertOk()
        ->assertJsonPath('status', 'completed');
});

it('refunds a completed payment', function () {
    $payment = Payment::factory()->completed()->create();

    $this->actingAs(comptable(), 'sanctum')
        ->postJson("/api/v1/admin/payments/{$payment->id}/refund")
        ->assertOk()
        ->assertJsonPath('status', 'refunded');
});

it('refuses to confirm an already-completed payment', function () {
    $payment = Payment::factory()->completed()->create();

    $this->actingAs(comptable(), 'sanctum')
        ->postJson("/api/v1/admin/payments/{$payment->id}/confirm")
        ->assertStatus(422);
});

it('refuses to refund a pending payment', function () {
    $payment = Payment::factory()->create();

    $this->actingAs(comptable(), 'sanctum')
        ->postJson("/api/v1/admin/payments/{$payment->id}/refund")
        ->assertStatus(422);
});

it('filters payments by order number', function () {
    $orderA = Order::factory()->create();
    $orderB = Order::factory()->create();
    Payment::factory()->create(['order_number' => $orderA->order_number]);
    Payment::factory()->create(['order_number' => $orderB->order_number]);

    $response = $this->actingAs(comptable(), 'sanctum')
        ->getJson("/api/v1/admin/payments?order_number={$orderA->order_number}");

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.order_number', $orderA->order_number);
});

it('caps per_page and clamps a non-positive value to 1', function () {
    Payment::factory()->count(3)->create();

    $this->actingAs(comptable(), 'sanctum')
        ->getJson('/api/v1/admin/payments?per_page=999999')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);

    $this->actingAs(comptable(), 'sanctum')
        ->getJson('/api/v1/admin/payments?per_page=-5')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 1);
});
