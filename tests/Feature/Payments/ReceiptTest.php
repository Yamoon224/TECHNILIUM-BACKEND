<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Payments\Application\RenderPaymentReceipt;
use App\Modules\Payments\Infrastructure\Models\Payment;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function receiptUser(Role $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

function paidFor(?User $customer, array $payment = [], array $order = []): Payment
{
    $placed = Order::factory()->create($order + ['customer_id' => $customer?->id, 'customer_name' => 'Awa Diallo', 'customer_phone' => '0700000001']);

    return Payment::factory()->completed()->create($payment + ['order_number' => $placed->order_number, 'amount_amount' => 118_000]);
}

it('restricts the staff receipt to the payments roles', function () {
    $payment = paidFor(null);

    $this->getJson("/api/v1/admin/payments/{$payment->id}/receipt")->assertUnauthorized();
    foreach ([Role::Support, Role::Logistique, Role::VendeurTiers, Role::Client] as $role) {
        $this->actingAs(receiptUser($role), 'sanctum')->get("/api/v1/admin/payments/{$payment->id}/receipt")->assertForbidden();
    }
});

it('gives staff a real PDF named after the payment', function () {
    $payment = paidFor(null);

    $response = $this->actingAs(receiptUser(Role::Comptable), 'sanctum')->get("/api/v1/admin/payments/{$payment->id}/receipt")->assertOk();

    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain(sprintf('REC-%06d.pdf', $payment->id))
        ->and($response->getContent())->toStartWith('%PDF-');
});

it('issues no receipt for a payment that was never received', function () {
    $placed = Order::factory()->create();
    $pending = Payment::factory()->create(['order_number' => $placed->order_number]);
    $accountant = receiptUser(Role::Comptable);

    $this->actingAs($accountant, 'sanctum')->getJson("/api/v1/admin/payments/{$pending->id}/receipt")->assertUnprocessable();
    $this->actingAs($accountant, 'sanctum')->getJson('/api/v1/admin/payments/999999/receipt')->assertNotFound();
});

it('prints the number, customer, order, method, reference and amount', function () {
    $payment = paidFor(null, ['method' => 'mobile_money', 'provider' => 'orange_money', 'provider_reference' => 'MM-12345678']);

    $page = app(RenderPaymentReceipt::class)->html($payment);

    expect($page)->toContain('REÇU DE PAIEMENT')->toContain(sprintf('REC-%06d', $payment->id))->toContain($payment->order_number)
        ->toContain('Awa Diallo')->toContain('Mobile money')->toContain('Orange Money')->toContain('MM-12345678')
        ->toContain("118\u{00A0}000\u{00A0}F\u{00A0}CFA");
});

it('shows what was refunded and the net kept', function () {
    $payment = paidFor(null, ['refunded_amount' => 18_000]);

    $page = app(RenderPaymentReceipt::class)->html($payment);

    expect($page)->toContain('Dont remboursé')->toContain("18\u{00A0}000\u{00A0}F\u{00A0}CFA")->toContain("100\u{00A0}000\u{00A0}F\u{00A0}CFA");
});

it('renders in English on request, French otherwise', function () {
    $payment = paidFor(null);

    expect(app(RenderPaymentReceipt::class)->html($payment, 'en'))->toContain('PAYMENT RECEIPT')->toContain('Amount received')->not->toContain('REÇU')
        ->and(app(RenderPaymentReceipt::class)->html($payment, 'de'))->toContain('REÇU DE PAIEMENT');
});

it('escapes hostile text', function () {
    $payment = paidFor(null, [], ['customer_name' => '<script>alert(1)</script>']);

    expect(app(RenderPaymentReceipt::class)->html($payment))->not->toContain('<script>')->toContain('&lt;script&gt;');
});

it('lets a customer list and download the receipts of their own order', function () {
    $customer = receiptUser(Role::Client);
    $payment = paidFor($customer);

    $this->actingAs($customer, 'sanctum')->getJson("/api/v1/customer/orders/{$payment->order_number}/receipts")
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.number', sprintf('REC-%06d', $payment->id))
        ->assertJsonPath('data.0.amount_amount', 118_000);

    $this->actingAs($customer, 'sanctum')->get("/api/v1/customer/payments/{$payment->id}/receipt")->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('lists only received payments, never a pending or failed one', function () {
    $customer = receiptUser(Role::Client);
    $payment = paidFor($customer);
    Payment::factory()->create(['order_number' => $payment->order_number]); // pending

    $this->actingAs($customer, 'sanctum')->getJson("/api/v1/customer/orders/{$payment->order_number}/receipts")->assertJsonCount(1, 'data');
});

it('answers 404, never 403, for another customer, a guest order or a pending payment', function () {
    $owner = receiptUser(Role::Client);
    $payment = paidFor($owner);
    $guest = paidFor(null);
    $placed = Order::factory()->create(['customer_id' => $owner->id]);
    $pending = Payment::factory()->create(['order_number' => $placed->order_number]);
    $intruder = receiptUser(Role::Client);

    $this->actingAs($intruder, 'sanctum')->get("/api/v1/customer/payments/{$payment->id}/receipt")->assertNotFound();
    $this->actingAs($intruder, 'sanctum')->getJson("/api/v1/customer/orders/{$payment->order_number}/receipts")->assertNotFound();
    $this->actingAs($owner, 'sanctum')->get("/api/v1/customer/payments/{$guest->id}/receipt")->assertNotFound();
    $this->actingAs($owner, 'sanctum')->get("/api/v1/customer/payments/{$pending->id}/receipt")->assertNotFound();
});

it('requires authentication for the customer receipts', function () {
    $payment = paidFor(receiptUser(Role::Client));

    $this->getJson("/api/v1/customer/payments/{$payment->id}/receipt")->assertUnauthorized();
    $this->getJson("/api/v1/customer/orders/{$payment->order_number}/receipts")->assertUnauthorized();
});
