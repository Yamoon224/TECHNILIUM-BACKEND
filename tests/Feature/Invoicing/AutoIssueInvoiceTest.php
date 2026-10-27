<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function autoOrder(): string
{
    $offer = Offer::factory()->create(['price_amount' => 59_000, 'stock_quantity' => 5]);

    return test()->postJson('/api/v1/orders', [
        'items' => [['offer_id' => $offer->id, 'quantity' => 2]],
        'customer_name' => 'Awa Diallo', 'customer_phone' => '0700000001', 'delivery_address' => 'Cocody',
    ])->assertCreated()->json('order_number');
}

function moveOrder(string $orderNumber, string $status): void
{
    $staff = User::factory()->create();
    $staff->assignRole(Role::OperateurCommandes->value);

    test()->actingAs($staff, 'sanctum')
        ->postJson("/api/v1/orders/{$orderNumber}/transition", ['status' => $status])->assertOk();
}

it('does not invoice an order that is only pending', function () {
    autoOrder();

    expect(Invoice::query()->count())->toBe(0);
});

it('invoices the order when it is confirmed, with VAT and lines', function () {
    $orderNumber = autoOrder();

    moveOrder($orderNumber, 'confirmed');

    $invoice = Invoice::query()->with('lines')->sole();
    expect($invoice->order_number)->toBe($orderNumber)
        ->and($invoice->number)->toBe('FAC-'.now()->year.'-000001')
        ->and($invoice->total_amount)->toBe(118_000)
        ->and($invoice->issued_by)->toBeNull()
        ->and($invoice->lines)->toHaveCount(1);
});

it('never issues a second invoice as the order moves on', function () {
    $orderNumber = autoOrder();

    foreach (['confirmed', 'shipped', 'delivered'] as $status) {
        moveOrder($orderNumber, $status);
    }

    expect(Invoice::query()->count())->toBe(1);
});

it('leaves a corrected invoice to the accountant once the first was credited', function () {
    $orderNumber = autoOrder();
    moveOrder($orderNumber, 'confirmed');
    $invoice = Invoice::query()->sole();

    $accountant = User::factory()->create();
    $accountant->assignRole(Role::Comptable->value);
    $this->actingAs($accountant, 'sanctum')
        ->postJson("/api/v1/admin/invoices/{$invoice->id}/credit-note", ['reason' => 'Erreur'])->assertCreated();

    moveOrder($orderNumber, 'shipped');

    expect(Invoice::query()->where('type', 'invoice')->count())->toBe(1);
});

it('does not invoice a cancelled order', function () {
    $orderNumber = autoOrder();

    moveOrder($orderNumber, 'cancelled');

    expect(Invoice::query()->count())->toBe(0);
});

it('can be switched off', function () {
    config(['invoicing.auto_issue' => false]);
    $orderNumber = autoOrder();

    moveOrder($orderNumber, 'confirmed');

    expect(Invoice::query()->count())->toBe(0);

    // The accountant can still invoice by hand.
    $accountant = User::factory()->create();
    $accountant->assignRole(Role::Comptable->value);
    $this->actingAs($accountant, 'sanctum')
        ->postJson('/api/v1/admin/invoices', ['order_number' => $orderNumber])->assertCreated();
});

it('rejects a manual invoice for an order already invoiced automatically', function () {
    $orderNumber = autoOrder();
    moveOrder($orderNumber, 'confirmed');

    $accountant = User::factory()->create();
    $accountant->assignRole(Role::Comptable->value);
    $this->actingAs($accountant, 'sanctum')
        ->postJson('/api/v1/admin/invoices', ['order_number' => $orderNumber])->assertUnprocessable();
});

it('never blocks the order status change when invoicing fails', function () {
    $orderNumber = autoOrder();
    $this->mock(OrderLookupGatewayInterface::class)->shouldReceive('find')->andThrow(new RuntimeException('boom'));

    moveOrder($orderNumber, 'confirmed'); // still answers 200

    expect(Invoice::query()->count())->toBe(0);
});

it('lets the customer download the automatic invoice from their account', function () {
    $customer = User::factory()->create();
    $customer->assignRole(Role::Client->value);
    $offer = Offer::factory()->create(['price_amount' => 10_000, 'stock_quantity' => 5]);
    $orderNumber = $this->actingAs($customer, 'sanctum')->postJson('/api/v1/orders', [
        'items' => [['offer_id' => $offer->id, 'quantity' => 1]],
        'customer_name' => 'Awa Diallo', 'customer_phone' => '0700000001', 'delivery_address' => 'Cocody',
    ])->assertCreated()->json('order_number');

    moveOrder($orderNumber, 'confirmed');
    $invoice = Invoice::query()->sole();

    $this->actingAs($customer, 'sanctum')->get("/api/v1/customer/invoices/{$invoice->id}/pdf")->assertOk();
});
