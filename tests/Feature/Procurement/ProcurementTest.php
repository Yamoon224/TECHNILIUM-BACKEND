<?php

declare(strict_types=1);

use App\Modules\Accounting\Infrastructure\Models\JournalEntry;
use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Inventory\Infrastructure\Models\StockMovement;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use App\Modules\Procurement\Infrastructure\Models\OfferCost;
use App\Modules\Procurement\Infrastructure\Models\PurchaseOrder;
use App\Modules\Procurement\Infrastructure\Models\Supplier;
use App\Modules\Procurement\Infrastructure\Models\SupplierInvoice;
use App\Modules\Shared\Events\StockConsumed;
use App\Modules\Vendors\Infrastructure\Models\Vendor;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function buyerStaff(Role $role = Role::ResponsableAchats): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

/** A placed purchase order: 10 × offer at 100, optionally a second offer. */
function placedOrder(User $buyer, ?Offer $offer = null, int $quantity = 10, int $unitCost = 100, ?Supplier $supplier = null): array
{
    $offer ??= Offer::factory()->create(['stock_quantity' => 0]);
    $supplier ??= Supplier::factory()->create();

    $created = test()->actingAs($buyer, 'sanctum')->postJson('/api/v1/admin/purchase-orders', [
        'supplier_id' => $supplier->id,
        'lines' => [['offer_id' => $offer->id, 'quantity' => $quantity, 'unit_cost_amount' => $unitCost]],
    ])->assertCreated();

    test()->actingAs($buyer, 'sanctum')->postJson("/api/v1/admin/purchase-orders/{$created->json('id')}/place")->assertOk();

    return [PurchaseOrder::query()->findOrFail($created->json('id')), $offer, $created->json('lines.0.id')];
}

function receive(User $buyer, PurchaseOrder $order, int $lineId, int $accepted, int $rejected = 0)
{
    return test()->actingAs($buyer, 'sanctum')->postJson("/api/v1/admin/purchase-orders/{$order->id}/receipts", [
        'lines' => [['line_id' => $lineId, 'accepted' => $accepted, 'rejected' => $rejected]],
    ]);
}

/** The trial balance is the accountants' view, not the buyer's: read it as one. */
function ledger(): array
{
    return test()->actingAs(buyerStaff(Role::Comptable), 'sanctum')->getJson('/api/v1/admin/accounting/trial-balance')->assertOk()->json();
}

function balances(?User $ignored = null): array
{
    return collect(ledger()['accounts'])->keyBy('code')->all();
}

it('restricts procurement to purchasing and administrators', function () {
    $this->getJson('/api/v1/admin/suppliers')->assertUnauthorized();

    foreach ([Role::Comptable, Role::GestionnaireStock, Role::Support, Role::VendeurTiers, Role::Client] as $role) {
        $this->actingAs(buyerStaff($role), 'sanctum')->getJson('/api/v1/admin/suppliers')->assertForbidden();
        $this->actingAs(buyerStaff($role), 'sanctum')->getJson('/api/v1/admin/purchase-orders')->assertForbidden();
    }

    $this->actingAs(buyerStaff(Role::Administrateur), 'sanctum')->getJson('/api/v1/admin/suppliers')->assertOk();
});

it('creates, updates, filters and deactivates suppliers', function () {
    $buyer = buyerStaff();

    $created = $this->actingAs($buyer, 'sanctum')
        ->postJson('/api/v1/admin/suppliers', ['name' => 'Tech Import SARL', 'payment_terms_days' => 45, 'email' => 'contact@techimport.example'])
        ->assertCreated()->assertJsonPath('payment_terms_days', 45)->assertJsonPath('active', true);

    $this->actingAs($buyer, 'sanctum')->postJson('/api/v1/admin/suppliers', ['name' => ''])->assertUnprocessable();
    $this->actingAs($buyer, 'sanctum')->postJson('/api/v1/admin/suppliers', ['name' => 'X Co', 'email' => 'not-an-email'])->assertUnprocessable();
    $this->actingAs($buyer, 'sanctum')->postJson('/api/v1/admin/suppliers', ['name' => 'Y Co', 'payment_terms_days' => 999])->assertUnprocessable();

    $this->actingAs($buyer, 'sanctum')->patchJson("/api/v1/admin/suppliers/{$created->json('id')}", ['active' => false])
        ->assertOk()->assertJsonPath('active', false)->assertJsonPath('name', 'Tech Import SARL');

    Supplier::factory()->create(['name' => 'Autre Fournisseur']);
    $this->actingAs($buyer, 'sanctum')->getJson('/api/v1/admin/suppliers?active=1')->assertJsonCount(1, 'data');
    $this->actingAs($buyer, 'sanctum')->getJson('/api/v1/admin/suppliers?q=import')->assertJsonCount(1, 'data');
});

it('only lets Technilium buy its own stock, from an active supplier, without duplicate lines', function () {
    $buyer = buyerStaff();
    $supplier = Supplier::factory()->create();
    $vendorOffer = Offer::factory()->fromVendor(Vendor::factory()->active()->create()->id)->create();
    $own = Offer::factory()->create();
    $line = fn (int $offerId) => ['offer_id' => $offerId, 'quantity' => 5, 'unit_cost_amount' => 100];
    $post = fn (array $body) => $this->actingAs($buyer, 'sanctum')->postJson('/api/v1/admin/purchase-orders', $body);

    $post(['supplier_id' => $supplier->id, 'lines' => [$line($own->id)]])->assertCreated()->assertJsonPath('status', 'draft');
    $post(['supplier_id' => $supplier->id, 'lines' => [$line($vendorOffer->id)]])->assertUnprocessable();
    $post(['supplier_id' => $supplier->id, 'lines' => [$line(999_999)]])->assertUnprocessable();
    $post(['supplier_id' => $supplier->id, 'lines' => [$line($own->id), $line($own->id)]])->assertUnprocessable();
    $post(['supplier_id' => Supplier::factory()->create(['active' => false])->id, 'lines' => [$line($own->id)]])->assertUnprocessable();
    $post(['supplier_id' => $supplier->id, 'lines' => []])->assertUnprocessable();
    $post(['supplier_id' => $supplier->id, 'lines' => [['offer_id' => $own->id, 'quantity' => 0, 'unit_cost_amount' => 100]]])->assertUnprocessable();
});

it('sends a draft to the supplier, and cancels only an order with nothing received', function () {
    $buyer = buyerStaff();
    [$order, , $lineId] = placedOrder($buyer);

    expect($order->fresh()->status->value)->toBe('ordered');
    $this->actingAs($buyer, 'sanctum')->postJson("/api/v1/admin/purchase-orders/{$order->id}/place")->assertUnprocessable();

    receive($buyer, $order, $lineId, 3)->assertCreated();
    $this->actingAs($buyer, 'sanctum')->postJson("/api/v1/admin/purchase-orders/{$order->id}/cancel")->assertUnprocessable();

    [$other] = placedOrder($buyer);
    $this->actingAs($buyer, 'sanctum')->postJson("/api/v1/admin/purchase-orders/{$other->id}/cancel")
        ->assertOk()->assertJsonPath('status', 'cancelled');
});

it('receives goods: stocks them with an audited movement and books their value', function () {
    $buyer = buyerStaff();
    [$order, $offer, $lineId] = placedOrder($buyer, quantity: 10, unitCost: 100);

    receive($buyer, $order, $lineId, 10)
        ->assertCreated()
        ->assertJsonPath('status', 'received')
        ->assertJsonPath('lines.0.quantity_received', 10)
        ->assertJsonPath('lines.0.outstanding', 0);

    expect($offer->fresh()->stock_quantity)->toBe(10);
    $movement = StockMovement::query()->sole();
    expect($movement->quantity_delta)->toBe(10)->and($movement->reason)->toContain($order->number());

    $books = balances($buyer);
    expect($books['311']['debit'])->toBe(1_000)->and($books['4012']['credit'])->toBe(1_000)
        ->and($books['4013']['credit'])->toBe(0)
        ->and($books)->toHaveKey('521');
});

it('handles a partial receipt, then the rest, tracking what is outstanding', function () {
    $buyer = buyerStaff();
    [$order, $offer, $lineId] = placedOrder($buyer, quantity: 10);

    receive($buyer, $order, $lineId, 4)->assertCreated()->assertJsonPath('status', 'partially_received')->assertJsonPath('lines.0.outstanding', 6);
    receive($buyer, $order, $lineId, 6)->assertCreated()->assertJsonPath('status', 'received');

    expect($offer->fresh()->stock_quantity)->toBe(10)->and(StockMovement::query()->count())->toBe(2);
});

it('does not stock what was refused at inspection, and still completes the order', function () {
    $buyer = buyerStaff();
    [$order, $offer, $lineId] = placedOrder($buyer, quantity: 10);

    receive($buyer, $order, $lineId, 7, 3)
        ->assertCreated()
        ->assertJsonPath('status', 'received')
        ->assertJsonPath('lines.0.quantity_received', 7)
        ->assertJsonPath('lines.0.quantity_rejected', 3);

    expect($offer->fresh()->stock_quantity)->toBe(7);
    // Only the 7 accepted units are booked: 7 × 100.
    expect(balances($buyer)['311']['debit'])->toBe(700);
});

it('books and stocks nothing for a receipt that only refuses goods', function () {
    $buyer = buyerStaff();
    [$order, $offer, $lineId] = placedOrder($buyer, quantity: 10);

    receive($buyer, $order, $lineId, 0, 10)->assertCreated()->assertJsonPath('status', 'received');

    expect($offer->fresh()->stock_quantity)->toBe(0)
        ->and(StockMovement::query()->count())->toBe(0)
        ->and(JournalEntry::query()->where('source_type', 'purchase_receipt')->count())->toBe(0);
});

it('refuses a receipt beyond the outstanding quantity, on a foreign line, empty, or on a draft', function () {
    $buyer = buyerStaff();
    [$order, , $lineId] = placedOrder($buyer, quantity: 10);
    [, , $otherLineId] = placedOrder($buyer);

    receive($buyer, $order, $lineId, 11)->assertUnprocessable();
    receive($buyer, $order, $lineId, 6, 5)->assertUnprocessable();
    receive($buyer, $order, $otherLineId, 1)->assertUnprocessable();
    receive($buyer, $order, $lineId, 0, 0)->assertUnprocessable();

    $draft = $this->actingAs($buyer, 'sanctum')->postJson('/api/v1/admin/purchase-orders', [
        'supplier_id' => Supplier::factory()->create()->id,
        'lines' => [['offer_id' => Offer::factory()->create()->id, 'quantity' => 5, 'unit_cost_amount' => 10]],
    ]);
    $this->actingAs($buyer, 'sanctum')->postJson("/api/v1/admin/purchase-orders/{$draft->json('id')}/receipts", [
        'lines' => [['line_id' => $draft->json('lines.0.id'), 'accepted' => 5]],
    ])->assertUnprocessable();

    // None of the refusals left a trace.
    expect(StockMovement::query()->count())->toBe(0);
});

it('lets a received order take no more goods', function () {
    $buyer = buyerStaff();
    [$order, , $lineId] = placedOrder($buyer, quantity: 5);

    receive($buyer, $order, $lineId, 5)->assertCreated();
    receive($buyer, $order, $lineId, 1)->assertUnprocessable();
});

it('spreads landed costs over what is received and books them apart', function () {
    $buyer = buyerStaff();
    [$order, $offer, $lineId] = placedOrder($buyer, quantity: 10, unitCost: 100);   // goods 1 000

    $this->actingAs($buyer, 'sanctum')->postJson("/api/v1/admin/purchase-orders/{$order->id}/landed-costs", ['label' => 'Transport', 'amount' => 100])
        ->assertCreated()->assertJsonPath('landed_total', 100);
    $this->actingAs($buyer, 'sanctum')->postJson("/api/v1/admin/purchase-orders/{$order->id}/landed-costs", ['label' => 'Douane', 'amount' => 100])
        ->assertCreated()->assertJsonPath('landed_total', 200);

    // Half the goods arrive: half the costs (100) are carried.
    $first = receive($buyer, $order, $lineId, 5)->assertCreated();
    expect($first->json('receipts.0.landed_amount'))->toBe(100);

    receive($buyer, $order, $lineId, 5)->assertCreated();

    $show = $this->actingAs($buyer, 'sanctum')->getJson("/api/v1/admin/purchase-orders/{$order->id}")->assertOk();
    expect(collect($show->json('receipts'))->sum('landed_amount'))->toBe(200);

    $books = balances($buyer);
    // Stock carries goods + landed costs; suppliers and carriers are owed apart.
    expect($books['311']['debit'])->toBe(1_200)->and($books['4012']['credit'])->toBe(1_000)->and($books['4013']['credit'])->toBe(200);
    expect($books['311']['debit'])->toBe($books['4012']['credit'] + $books['4013']['credit']);
});

it('catches a landed cost recorded after a first receipt on the next one', function () {
    $buyer = buyerStaff();
    [$order, , $lineId] = placedOrder($buyer, quantity: 10, unitCost: 100);

    receive($buyer, $order, $lineId, 5)->assertCreated();   // no landed cost yet
    $this->actingAs($buyer, 'sanctum')->postJson("/api/v1/admin/purchase-orders/{$order->id}/landed-costs", ['label' => 'Fret', 'amount' => 200])->assertCreated();
    receive($buyer, $order, $lineId, 5)->assertCreated();

    $show = $this->actingAs($buyer, 'sanctum')->getJson("/api/v1/admin/purchase-orders/{$order->id}");
    expect(collect($show->json('receipts'))->sum('landed_amount'))->toBe(200);
});

it('refuses a landed cost on a received or cancelled order', function () {
    $buyer = buyerStaff();
    [$received, , $lineId] = placedOrder($buyer, quantity: 2);
    receive($buyer, $received, $lineId, 2)->assertCreated();
    [$cancelled] = placedOrder($buyer);
    $this->actingAs($buyer, 'sanctum')->postJson("/api/v1/admin/purchase-orders/{$cancelled->id}/cancel");

    foreach ([$received, $cancelled] as $order) {
        $this->actingAs($buyer, 'sanctum')->postJson("/api/v1/admin/purchase-orders/{$order->id}/landed-costs", ['label' => 'Fret', 'amount' => 50])->assertUnprocessable();
    }
});

it('computes the weighted-average cost across receipts, landed costs included', function () {
    $buyer = buyerStaff();
    $offer = Offer::factory()->create(['stock_quantity' => 0]);
    $supplier = Supplier::factory()->create();

    // First purchase: 10 at 100, no landed costs -> average 100.
    [$one, , $line1] = placedOrder($buyer, $offer, 10, 100, $supplier);
    receive($buyer, $one, $line1, 10)->assertCreated();
    expect(OfferCost::query()->find($offer->id)->average_cost_amount)->toBe(100);

    // Second purchase: 10 at 150 + 500 landed (= 200 each all-in). 10 × 100 + 2 000 over 20 = 150.
    [$two, , $line2] = placedOrder($buyer, $offer, 10, 150, $supplier);
    $this->actingAs($buyer, 'sanctum')->postJson("/api/v1/admin/purchase-orders/{$two->id}/landed-costs", ['label' => 'Fret', 'amount' => 500]);
    receive($buyer, $two, $line2, 10)->assertCreated();

    expect(OfferCost::query()->find($offer->id)->average_cost_amount)->toBe(150)
        ->and($offer->fresh()->stock_quantity)->toBe(20);
});

it('values the stock at the weighted-average cost', function () {
    $buyer = buyerStaff();
    [$order, $offer, $lineId] = placedOrder($buyer, quantity: 10, unitCost: 100);
    receive($buyer, $order, $lineId, 10)->assertCreated();

    $this->actingAs($buyer, 'sanctum')->getJson('/api/v1/admin/procurement/stock-valuation')
        ->assertOk()
        ->assertJsonPath('total_value', 1_000)
        ->assertJsonPath('offers.0.offer_id', $offer->id)
        ->assertJsonPath('offers.0.average_cost', 100)
        ->assertJsonPath('offers.0.stock', 10);
});

it('records supplier invoices with a due date from the supplier\'s terms, never entered twice', function () {
    $buyer = buyerStaff();
    $supplier = Supplier::factory()->create(['payment_terms_days' => 45]);
    $other = Supplier::factory()->create();
    $post = fn (array $b) => $this->actingAs($buyer, 'sanctum')->postJson('/api/v1/admin/supplier-invoices', $b);
    $base = ['supplier_id' => $supplier->id, 'number' => 'F-100', 'kind' => 'goods', 'amount' => 50_000, 'invoice_date' => '2026-10-01'];

    $post($base)->assertCreated()->assertJsonPath('due_date', '2026-11-15')->assertJsonPath('outstanding', 50_000);

    $post($base)->assertUnprocessable();                                            // same supplier, same number
    $post(['supplier_id' => $other->id] + $base)->assertCreated();                  // another supplier may reuse it
    $post(['number' => 'F-101', 'due_date' => '2026-09-01'] + $base)->assertUnprocessable();   // due before invoice
    $post(['number' => 'F-102', 'kind' => 'other'] + $base)->assertUnprocessable();
    $post(['number' => 'F-103', 'amount' => 0] + $base)->assertUnprocessable();
});

it('links an invoice only to one of the same supplier\'s purchase orders', function () {
    $buyer = buyerStaff();
    [$order] = placedOrder($buyer);

    $this->actingAs($buyer, 'sanctum')->postJson('/api/v1/admin/supplier-invoices', [
        'supplier_id' => Supplier::factory()->create()->id, 'purchase_order_id' => $order->id,
        'number' => 'F-1', 'kind' => 'goods', 'amount' => 1_000, 'invoice_date' => '2026-10-01',
    ])->assertUnprocessable();

    $this->actingAs($buyer, 'sanctum')->postJson('/api/v1/admin/supplier-invoices', [
        'supplier_id' => $order->supplier_id, 'purchase_order_id' => $order->id,
        'number' => 'F-1', 'kind' => 'goods', 'amount' => 1_000, 'invoice_date' => '2026-10-01',
    ])->assertCreated()->assertJsonPath('purchase_order_id', $order->id);
});

it('pays a supplier invoice in parts, never beyond what is owed, and books each payment', function () {
    $buyer = buyerStaff();
    $invoice = SupplierInvoice::factory()->create(['amount' => 100_000]);
    $url = "/api/v1/admin/supplier-invoices/{$invoice->id}/payments";
    $pay = fn (array $b) => $this->actingAs($buyer, 'sanctum')->postJson($url, $b + ['method' => 'bank_transfer']);

    $pay(['amount' => 40_000, 'reference' => 'VIR-1'])->assertOk()->assertJsonPath('paid_amount', 40_000)->assertJsonPath('outstanding', 60_000)->assertJsonPath('settled', false);
    $pay(['amount' => 60_001])->assertUnprocessable();
    $pay(['amount' => 0])->assertUnprocessable();
    $pay(['method' => 'barter'])->assertUnprocessable();
    $pay([])->assertOk()->assertJsonPath('settled', true)->assertJsonPath('paid_amount', 100_000);   // defaults to the remainder
    $pay(['amount' => 1])->assertUnprocessable();                                                     // already settled

    $books = balances($buyer);
    expect($books['4012']['debit'])->toBe(100_000)->and($books['521']['credit'])->toBe(100_000);
    expect(JournalEntry::query()->where('source_type', 'supplier_payment')->count())->toBe(2);
});

it('settles the carriers\' payable, not the supplier\'s, for a landed-cost invoice', function () {
    $buyer = buyerStaff();
    $invoice = SupplierInvoice::factory()->create(['kind' => 'landed_cost', 'amount' => 5_000]);

    $this->actingAs($buyer, 'sanctum')->postJson("/api/v1/admin/supplier-invoices/{$invoice->id}/payments", ['method' => 'cash'])->assertOk();

    $books = balances($buyer);
    expect($books['4013']['debit'])->toBe(5_000)->and($books['4012']['debit'])->toBe(0);
});

it('lists invoices by status and totals what is owed, flagging what is overdue', function () {
    $buyer = buyerStaff();
    $acme = Supplier::factory()->create(['name' => 'Acme']);
    $zeta = Supplier::factory()->create(['name' => 'Zeta']);
    SupplierInvoice::factory()->create(['supplier_id' => $acme->id, 'amount' => 100_000, 'due_date' => now()->subDays(5)->toDateString()]);
    SupplierInvoice::factory()->create(['supplier_id' => $acme->id, 'amount' => 50_000, 'paid_amount' => 20_000, 'due_date' => now()->addDays(10)->toDateString()]);
    SupplierInvoice::factory()->create(['supplier_id' => $zeta->id, 'amount' => 30_000, 'paid_amount' => 30_000]);   // settled

    $get = fn (string $q) => $this->actingAs($buyer, 'sanctum')->getJson("/api/v1/admin/supplier-invoices?{$q}");
    $get('status=open')->assertJsonCount(2, 'data');
    $get('status=settled')->assertJsonCount(1, 'data');
    $get('status=overdue')->assertJsonCount(1, 'data')->assertJsonPath('data.0.overdue', true);
    $get("supplier_id={$zeta->id}")->assertJsonCount(1, 'data');

    $this->actingAs($buyer, 'sanctum')->getJson('/api/v1/admin/procurement/payables')
        ->assertOk()
        ->assertJsonPath('total_outstanding', 130_000)     // 100 000 + (50 000 − 20 000)
        ->assertJsonPath('total_overdue', 100_000)
        ->assertJsonCount(1, 'by_supplier')
        ->assertJsonPath('by_supplier.0.supplier_name', 'Acme');
});

/** An own-stock order for $qty × the offer, delivered through the staff endpoint. */
function deliverOwnStockOrder(Offer $offer, int $qty, ?int $vendorId = null): Order
{
    $order = Order::factory()->create(['status' => OrderStatus::Shipped]);
    OrderItem::factory()->create([
        'order_id' => $order->id, 'offer_id' => $offer->id, 'vendor_id' => $vendorId,
        'unit_price_amount' => 500, 'quantity' => $qty, 'line_total_amount' => 500 * $qty,
    ]);

    test()->actingAs(buyerStaff(Role::OperateurCommandes), 'sanctum')
        ->postJson("/api/v1/orders/{$order->order_number}/transition", ['status' => 'delivered'])->assertOk();

    return $order;
}

it('books the cost of goods sold at average cost when own-stock goods are delivered', function () {
    $buyer = buyerStaff();
    [$po, $offer, $lineId] = placedOrder($buyer, quantity: 10, unitCost: 100);
    receive($buyer, $po, $lineId, 10)->assertCreated();

    $order = deliverOwnStockOrder($offer, 3);

    $entry = JournalEntry::query()->with('lines')->where('source_type', 'cogs')->sole();
    expect($entry->reference)->toBe($order->order_number)
        ->and($entry->lines->firstWhere('account_code', '601')->debit)->toBe(300)    // 3 × 100
        ->and($entry->lines->firstWhere('account_code', '311')->credit)->toBe(300);

    // Stock account: +1 000 received, −300 sold.
    $books = balances($buyer);
    expect($books['311']['balance'])->toBe(700);
    expect($books['601']['debit'])->toBe(300);
});

it('books no cost of sales for a third-party vendor\'s lines, or for goods never purchased here', function () {
    $buyer = buyerStaff();
    $vendor = Vendor::factory()->active()->create();
    $vendorOffer = Offer::factory()->fromVendor($vendor->id)->create();
    OfferCost::query()->create(['offer_id' => $vendorOffer->id, 'average_cost_amount' => 999]);   // even with a stray cost row

    deliverOwnStockOrder($vendorOffer, 2, vendorId: $vendor->id);
    deliverOwnStockOrder(Offer::factory()->create(), 2);   // own stock, but no purchase history

    expect(JournalEntry::query()->where('source_type', 'cogs')->count())->toBe(0);
});

it('does not book the cost of sales twice if the event is replayed', function () {
    $buyer = buyerStaff();
    [$po, $offer, $lineId] = placedOrder($buyer, quantity: 10, unitCost: 100);
    receive($buyer, $po, $lineId, 10)->assertCreated();
    $order = deliverOwnStockOrder($offer, 2);

    event(new StockConsumed($order->id, $order->order_number, 200, 'XOF'));
    event(new StockConsumed($order->id, $order->order_number, 200, 'XOF'));

    expect(JournalEntry::query()->where('source_type', 'cogs')->count())->toBe(1);
});

it('keeps the ledger balanced across the whole purchase cycle', function () {
    $buyer = buyerStaff();
    [$po, $offer, $lineId] = placedOrder($buyer, quantity: 10, unitCost: 100);
    $this->actingAs($buyer, 'sanctum')->postJson("/api/v1/admin/purchase-orders/{$po->id}/landed-costs", ['label' => 'Fret', 'amount' => 333]);
    receive($buyer, $po, $lineId, 4)->assertCreated();
    receive($buyer, $po, $lineId, 6)->assertCreated();
    deliverOwnStockOrder($offer, 4);

    $invoice = SupplierInvoice::factory()->create(['amount' => 1_000]);
    $this->actingAs($buyer, 'sanctum')->postJson("/api/v1/admin/supplier-invoices/{$invoice->id}/payments", ['method' => 'bank_transfer', 'amount' => 700])->assertOk();

    $totals = ledger();
    expect($totals['total_debit'])->toBe($totals['total_credit'])->and($totals['total_debit'])->toBeGreaterThan(0);
});

it('audits purchasing actions, refused ones included', function () {
    $buyer = buyerStaff();
    [$po, , $lineId] = placedOrder($buyer, quantity: 2);
    receive($buyer, $po, $lineId, 99)->assertUnprocessable();

    expect(\App\Modules\Audit\Infrastructure\Models\AuditLog::query()->where('path', 'like', '%/receipts')->where('status', 422)->count())->toBe(1);
});

/* ---------------- cost of sales given back on a resellable customer return ---------------- */

/** Opens, accepts and receives a return of $qty units; $restock = the inspector's decision. */
function returnUnits(Order $order, Offer $offer, int $qty, bool $restock): int
{
    $admin = buyerStaff(Role::Administrateur);

    $id = test()->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/returns', [
        'order_number' => $order->order_number, 'reason' => 'Défaut',
        'items' => [['offer_id' => $offer->id, 'quantity' => $qty]],
    ])->assertCreated()->json('id');

    test()->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/returns/{$id}/accept")->assertOk();
    test()->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/returns/{$id}/receive", ['restock' => $restock])->assertOk();

    return $id;
}

it('gives the cost of sales back when resellable goods return to stock', function () {
    $buyer = buyerStaff();
    [$po, $offer, $lineId] = placedOrder($buyer, quantity: 10, unitCost: 100);
    receive($buyer, $po, $lineId, 10)->assertCreated();
    $order = deliverOwnStockOrder($offer, 3);          // cost of sales 3 × 100 = 300

    $returnId = returnUnits($order, $offer, 2, restock: true);

    $entry = JournalEntry::query()->with('lines')->where('source_type', 'cogs_return')->sole();
    expect($entry->source_id)->toBe($returnId)
        ->and($entry->reference)->toBe($order->order_number)
        ->and($entry->lines->firstWhere('account_code', '311')->debit)->toBe(200)    // stock up
        ->and($entry->lines->firstWhere('account_code', '601')->credit)->toBe(200);  // cost of sales down

    // 1 000 received − 300 sold + 200 returned; net cost of sales is the one unit really kept.
    $books = balances();
    expect($books['311']['balance'])->toBe(900)
        ->and($books['601']['balance'])->toBe(100)
        // The test order was built directly (no checkout), so its sale did not take units
        // out of physical stock: 10 received + the 2 returned units restocked.
        ->and($offer->fresh()->stock_quantity)->toBe(10 + 2);

    $totals = ledger();
    expect($totals['total_debit'])->toBe($totals['total_credit']);
});

it('gives nothing back when the inspector does not restock the goods', function () {
    $buyer = buyerStaff();
    [$po, $offer, $lineId] = placedOrder($buyer, quantity: 10, unitCost: 100);
    receive($buyer, $po, $lineId, 10)->assertCreated();
    $order = deliverOwnStockOrder($offer, 3);

    returnUnits($order, $offer, 2, restock: false);   // damaged: not back on sale

    expect(JournalEntry::query()->where('source_type', 'cogs_return')->count())->toBe(0)
        ->and(balances()['601']['balance'])->toBe(300);   // the cost stays: the goods are lost
});

it('gives nothing back for a third-party vendor\'s lines', function () {
    $vendor = Vendor::factory()->active()->create();
    $vendorOffer = Offer::factory()->fromVendor($vendor->id)->create(['stock_quantity' => 5]);
    $order = deliverOwnStockOrder($vendorOffer, 2, vendorId: $vendor->id);
    // Pretend a cost had been recognised for the order anyway.
    \App\Modules\Procurement\Infrastructure\Models\OrderCost::query()->create(['order_id' => $order->id, 'order_number' => $order->order_number, 'cost_amount' => 999]);
    OfferCost::query()->create(['offer_id' => $vendorOffer->id, 'average_cost_amount' => 100]);

    event(new \App\Modules\Shared\Events\ReturnReceived(7_001, $order->order_number, [['offer_id' => $vendorOffer->id, 'quantity' => 1]]));

    expect(JournalEntry::query()->where('source_type', 'cogs_return')->count())->toBe(0);
});

it('gives nothing back for an order whose cost of sales was never recognised', function () {
    $offer = Offer::factory()->create(['stock_quantity' => 5]);
    $order = deliverOwnStockOrder($offer, 2);   // own stock, but no purchase history: no cost recognised
    OfferCost::query()->create(['offer_id' => $offer->id, 'average_cost_amount' => 100]);   // a cost appears later

    event(new \App\Modules\Shared\Events\ReturnReceived(7_002, $order->order_number, [['offer_id' => $offer->id, 'quantity' => 1]]));

    expect(JournalEntry::query()->where('source_type', 'cogs_return')->count())->toBe(0);
});

it('never gives back more than was recognised, across several returns', function () {
    $buyer = buyerStaff();
    [$po, $offer, $lineId] = placedOrder($buyer, quantity: 10, unitCost: 100);
    receive($buyer, $po, $lineId, 10)->assertCreated();
    $order = deliverOwnStockOrder($offer, 3);            // recognised: 300

    $item = [['offer_id' => $offer->id, 'quantity' => 2]];
    event(new \App\Modules\Shared\Events\ReturnReceived(8_001, $order->order_number, $item));   // 200
    event(new \App\Modules\Shared\Events\ReturnReceived(8_002, $order->order_number, $item));   // would be 200, only 100 left
    event(new \App\Modules\Shared\Events\ReturnReceived(8_003, $order->order_number, $item));   // nothing left

    $amounts = JournalEntry::query()->with('lines')->where('source_type', 'cogs_return')->orderBy('id')->get()
        ->map(fn ($e) => $e->lines->firstWhere('account_code', '311')->debit)->all();

    expect($amounts)->toBe([200, 100])
        ->and(array_sum($amounts))->toBe(300)
        ->and(balances()['601']['balance'])->toBe(0);
});

it('gives the cost back once per return even if the event is replayed', function () {
    $buyer = buyerStaff();
    [$po, $offer, $lineId] = placedOrder($buyer, quantity: 10, unitCost: 100);
    receive($buyer, $po, $lineId, 10)->assertCreated();
    $order = deliverOwnStockOrder($offer, 3);

    $event = new \App\Modules\Shared\Events\ReturnReceived(9_001, $order->order_number, [['offer_id' => $offer->id, 'quantity' => 1]]);
    event($event);
    event($event);

    expect(JournalEntry::query()->where('source_type', 'cogs_return')->count())->toBe(1)
        ->and(\App\Modules\Procurement\Infrastructure\Models\CostReversal::query()->count())->toBe(1);
});

it('recognises the cost of sales once even if the delivery is announced twice', function () {
    $buyer = buyerStaff();
    [$po, $offer, $lineId] = placedOrder($buyer, quantity: 10, unitCost: 100);
    receive($buyer, $po, $lineId, 10)->assertCreated();
    $order = deliverOwnStockOrder($offer, 3);

    event(new \App\Modules\Shared\Events\OrderStatusChanged($order->order_number, 'X', '0700000000', 'delivered', $order->id));

    expect(JournalEntry::query()->where('source_type', 'cogs')->count())->toBe(1)
        ->and(\App\Modules\Procurement\Infrastructure\Models\OrderCost::query()->sole()->cost_amount)->toBe(300);
});

it('uses the cost recognised at delivery as the ceiling even when the average cost has since changed', function () {
    $buyer = buyerStaff();
    [$po, $offer, $lineId] = placedOrder($buyer, quantity: 10, unitCost: 100);
    receive($buyer, $po, $lineId, 10)->assertCreated();
    $order = deliverOwnStockOrder($offer, 2);            // recognised: 200

    OfferCost::query()->where('offer_id', $offer->id)->update(['average_cost_amount' => 500]);   // cost went up since

    event(new \App\Modules\Shared\Events\ReturnReceived(9_101, $order->order_number, [['offer_id' => $offer->id, 'quantity' => 2]]));

    // 2 × 500 = 1 000 would exceed what was ever recognised: capped at 200.
    $entry = JournalEntry::query()->with('lines')->where('source_type', 'cogs_return')->sole();
    expect($entry->lines->firstWhere('account_code', '311')->debit)->toBe(200);
});
