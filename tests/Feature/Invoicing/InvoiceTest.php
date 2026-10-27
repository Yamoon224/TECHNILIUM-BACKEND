<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Invoicing\Domain\Exceptions\InvoiceException;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function invoicingUser(Role $role = Role::Comptable): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

function confirmedOrder(): Order
{
    $order = Order::factory()->create(['status' => OrderStatus::Confirmed, 'subtotal_amount' => 118_000]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'brand' => 'Samsung',
        'product_name' => 'Galaxy A15',
        'unit_price_amount' => 59_000,
        'quantity' => 2,
        'line_total_amount' => 118_000,
    ]);

    return $order;
}

it('rejects anonymous and non-finance roles', function () {
    $this->getJson('/api/v1/admin/invoices')->assertUnauthorized();

    $this->actingAs(invoicingUser(Role::OperateurCommandes), 'sanctum')
        ->getJson('/api/v1/admin/invoices')
        ->assertForbidden();
});

it('issues a numbered invoice with VAT split and line snapshot', function () {
    $order = confirmedOrder();

    $response = $this->actingAs(invoicingUser(), 'sanctum')
        ->postJson('/api/v1/admin/invoices', ['order_number' => $order->order_number]);

    $response->assertCreated()
        ->assertJsonPath('number', 'FAC-'.now()->year.'-000001')
        ->assertJsonPath('type', 'invoice')
        ->assertJsonPath('net_amount', 100_000)
        ->assertJsonPath('vat_amount', 18_000)
        ->assertJsonPath('total_amount', 118_000)
        ->assertJsonPath('lines.0.description', 'Samsung Galaxy A15')
        ->assertJsonPath('lines.0.quantity', 2);
});

it('numbers invoices sequentially', function () {
    $user = invoicingUser();

    $first = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/admin/invoices', ['order_number' => confirmedOrder()->order_number]);
    $second = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/admin/invoices', ['order_number' => confirmedOrder()->order_number]);

    expect($first->json('number'))->toEndWith('000001')
        ->and($second->json('number'))->toEndWith('000002');
});

it('refuses to invoice an unknown, pending or cancelled order', function (?OrderStatus $status) {
    $orderNumber = 'TCH-UNKNOWN';

    if ($status !== null) {
        $orderNumber = Order::factory()->create(['status' => $status])->order_number;
    }

    $this->actingAs(invoicingUser(), 'sanctum')
        ->postJson('/api/v1/admin/invoices', ['order_number' => $orderNumber])
        ->assertUnprocessable();
})->with([null, OrderStatus::Pending, OrderStatus::Cancelled]);

it('refuses a second live invoice for the same order', function () {
    $order = confirmedOrder();
    $user = invoicingUser();

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/invoices', ['order_number' => $order->order_number])->assertCreated();
    $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/invoices', ['order_number' => $order->order_number])->assertUnprocessable();
});

it('cancels an invoice with a mirrored credit note, then allows a corrected invoice', function () {
    $order = confirmedOrder();
    $user = invoicingUser();

    $invoice = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/admin/invoices', ['order_number' => $order->order_number])->json();

    $credit = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/admin/invoices/{$invoice['id']}/credit-note", ['reason' => 'Erreur de prix']);

    $credit->assertCreated()
        ->assertJsonPath('type', 'credit_note')
        ->assertJsonPath('number', 'AV-'.now()->year.'-000001')
        ->assertJsonPath('credited_invoice_id', $invoice['id'])
        ->assertJsonPath('total_amount', 118_000)
        ->assertJsonCount(1, 'lines');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/admin/invoices', ['order_number' => $order->order_number])
        ->assertCreated()
        ->assertJsonPath('number', 'FAC-'.now()->year.'-000002');
});

it('refuses to credit an invoice twice or to credit a credit note', function () {
    $user = invoicingUser();
    $invoice = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/admin/invoices', ['order_number' => confirmedOrder()->order_number])->json();

    $credit = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/admin/invoices/{$invoice['id']}/credit-note", ['reason' => 'x'])->json();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/admin/invoices/{$invoice['id']}/credit-note", ['reason' => 'x'])
        ->assertUnprocessable();
    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/admin/invoices/{$credit['id']}/credit-note", ['reason' => 'x'])
        ->assertUnprocessable();
});

it('requires a reason for a credit note', function () {
    $invoice = Invoice::factory()->create();

    $this->actingAs(invoicingUser(), 'sanctum')
        ->postJson("/api/v1/admin/invoices/{$invoice->id}/credit-note", [])
        ->assertUnprocessable();
});

it('keeps issued documents immutable', function () {
    $invoice = Invoice::factory()->create();

    expect(fn () => $invoice->update(['total_amount' => 1]))->toThrow(InvoiceException::class);
    expect(fn () => $invoice->delete())->toThrow(InvoiceException::class);
});

it('lists, filters and shows documents', function () {
    $user = invoicingUser();
    $a = Invoice::factory()->create();
    Invoice::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/admin/invoices?order_number={$a->order_number}")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/admin/invoices/{$a->id}")
        ->assertOk()
        ->assertJsonPath('number', $a->number);
});
