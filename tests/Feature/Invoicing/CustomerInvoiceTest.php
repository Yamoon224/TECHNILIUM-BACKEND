<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Orders\Infrastructure\Models\Order;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function shopper(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Client->value);

    return $user;
}

/** An invoice (and its order) belonging to the given customer, or to a guest when null. */
function invoiceOf(?User $customer, string $orderNumber = 'TCH-20261027-AAA111'): Invoice
{
    Order::factory()->create(['order_number' => $orderNumber, 'customer_id' => $customer?->id]);
    $invoice = Invoice::factory()->create(['order_number' => $orderNumber, 'number' => 'FAC-2026-'.random_int(100000, 999999)]);
    $invoice->lines()->create(['description' => 'Article', 'quantity' => 1, 'unit_price_amount' => 1000, 'line_total_amount' => 1000]);

    return $invoice;
}

it('requires authentication', function () {
    $invoice = invoiceOf(shopper());

    $this->getJson("/api/v1/customer/invoices/{$invoice->id}/pdf")->assertUnauthorized();
    $this->getJson("/api/v1/customer/orders/{$invoice->order_number}/invoices")->assertUnauthorized();
});

it('lets a customer download the PDF of their own invoice', function () {
    $customer = shopper();
    $invoice = invoiceOf($customer);

    $response = $this->actingAs($customer, 'sanctum')->get("/api/v1/customer/invoices/{$invoice->id}/pdf")->assertOk();

    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain($invoice->number.'.pdf')
        ->and($response->getContent())->toStartWith('%PDF-');
});

it('lists the invoices and credit notes of their own order', function () {
    $customer = shopper();
    $invoice = invoiceOf($customer);
    Invoice::factory()->create([
        'order_number' => $invoice->order_number, 'number' => 'AV-2026-000001', 'type' => 'credit_note',
        'credited_invoice_id' => $invoice->id,
    ]);
    invoiceOf(shopper(), 'TCH-20261027-BBB222'); // someone else's, must not appear

    $this->actingAs($customer, 'sanctum')->getJson("/api/v1/customer/orders/{$invoice->order_number}/invoices")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.number', $invoice->number)
        ->assertJsonPath('data.1.number', 'AV-2026-000001');
});

it('answers 404, never 403, for another customer\'s invoice or order', function () {
    $invoice = invoiceOf(shopper());
    $intruder = shopper();

    $this->actingAs($intruder, 'sanctum')->get("/api/v1/customer/invoices/{$invoice->id}/pdf")->assertNotFound();
    $this->actingAs($intruder, 'sanctum')->getJson("/api/v1/customer/orders/{$invoice->order_number}/invoices")->assertNotFound();
});

it('keeps guest-order invoices out of every account', function () {
    $invoice = invoiceOf(null);

    $this->actingAs(shopper(), 'sanctum')->get("/api/v1/customer/invoices/{$invoice->id}/pdf")->assertNotFound();
});

it('answers 404 for unknown documents and orders', function () {
    $customer = shopper();

    $this->actingAs($customer, 'sanctum')->get('/api/v1/customer/invoices/999999/pdf')->assertNotFound();
    $this->actingAs($customer, 'sanctum')->getJson('/api/v1/customer/orders/TCH-NOPE/invoices')->assertNotFound();
});

it('does not give staff-only access through the customer route', function () {
    $invoice = invoiceOf(shopper());
    $accountant = User::factory()->create();
    $accountant->assignRole(Role::Comptable->value);

    // Staff use /admin/invoices; the customer route is strictly ownership-based.
    $this->actingAs($accountant, 'sanctum')->get("/api/v1/customer/invoices/{$invoice->id}/pdf")->assertNotFound();
});
