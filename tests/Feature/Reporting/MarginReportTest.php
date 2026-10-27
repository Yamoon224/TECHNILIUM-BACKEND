<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use App\Modules\Procurement\Infrastructure\Models\CostReversal;
use App\Modules\Procurement\Infrastructure\Models\OfferCost;
use App\Modules\Procurement\Infrastructure\Models\OrderCost;
use App\Modules\Procurement\Infrastructure\Models\OrderCostLine;
use App\Modules\Returns\Domain\ReturnStatus;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use App\Modules\Vendors\Infrastructure\Models\Vendor;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function marginReader(Role $role = Role::Comptable): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

/**
 * A delivered order. $lines: [offer, quantity, unit price, ?vendorId, ?cost]. A line with a cost
 * gets its cost of sales on record, as if recognised at delivery.
 */
function marginSale(string $day, array $lines, int $discount = 0): Order
{
    $itemsTotal = array_sum(array_map(fn ($l) => $l[1] * $l[2], $lines));

    $order = Order::factory()->create([
        'status' => OrderStatus::Delivered,
        'delivered_at' => $day.' 12:00:00',
        'subtotal_amount' => $itemsTotal - $discount,
        'discount_amount' => $discount,
    ]);

    $totalCost = 0;
    foreach ($lines as [$offer, $quantity, $unitPrice, $vendorId, $cost]) {
        OrderItem::factory()->create([
            'order_id' => $order->id, 'offer_id' => $offer->id, 'vendor_id' => $vendorId ?? null,
            'brand' => 'Samsung', 'product_name' => "Galaxy {$offer->id}",
            'unit_price_amount' => $unitPrice, 'quantity' => $quantity, 'line_total_amount' => $quantity * $unitPrice,
        ]);

        if (($cost ?? null) !== null) {
            OrderCostLine::query()->create(['order_id' => $order->id, 'offer_id' => $offer->id, 'quantity' => $quantity, 'cost_amount' => $cost]);
            $totalCost += $cost;
        }
    }

    if ($totalCost > 0) {
        OrderCost::query()->create(['order_id' => $order->id, 'order_number' => $order->order_number, 'cost_amount' => $totalCost]);
    }

    return $order;
}

function margin(string $query = 'from=2026-10-01&to=2026-10-31')
{
    return test()->actingAs(marginReader(), 'sanctum')->getJson("/api/v1/admin/reports/margin?{$query}");
}

it('restricts the margin report to administrators and accountants', function () {
    $this->getJson('/api/v1/admin/reports/margin')->assertUnauthorized();

    foreach ([Role::Support, Role::Marketing, Role::ResponsableAchats, Role::VendeurTiers] as $role) {
        $this->actingAs(marginReader($role), 'sanctum')->getJson('/api/v1/admin/reports/margin')->assertForbidden();
    }

    $this->actingAs(marginReader(Role::Administrateur), 'sanctum')->getJson('/api/v1/admin/reports/margin')->assertOk();
});

it('computes revenue excluding VAT, cost of sales and margin on Technilium\'s own stock', function () {
    $offer = Offer::factory()->create();
    // 3 units at 500 = 1 500 TTC -> 1 271 HT ; cost 300.
    marginSale('2026-10-10', [[$offer, 3, 500, null, 300]]);

    margin()->assertOk()
        ->assertJsonPath('scope', 'own_stock_only')
        ->assertJsonPath('vat_percent', 18)
        ->assertJsonPath('totals.orders', 1)
        ->assertJsonPath('totals.units', 3)
        ->assertJsonPath('totals.revenue', 1_271)
        ->assertJsonPath('totals.cost', 300)
        ->assertJsonPath('totals.margin', 971)
        ->assertJsonPath('totals.margin_percent', 76.4);
});

it('leaves a third-party vendor\'s lines and orders out of the margin', function () {
    $own = Offer::factory()->create();
    $vendor = Vendor::factory()->active()->create();
    $vendorOffer = Offer::factory()->fromVendor($vendor->id)->create();

    // A mixed order: only the own line counts.
    marginSale('2026-10-10', [[$own, 1, 11_800, null, 4_000], [$vendorOffer, 5, 20_000, $vendor->id, null]]);
    // A vendor-only order: not in the report at all.
    marginSale('2026-10-11', [[$vendorOffer, 2, 20_000, $vendor->id, null]]);

    margin()->assertOk()
        ->assertJsonPath('totals.orders', 1)
        ->assertJsonPath('totals.revenue', 10_000)
        ->assertJsonPath('totals.cost', 4_000)
        ->assertJsonPath('totals.margin', 6_000)
        ->assertJsonCount(1, 'by_product');
});

it('bears the discount on own lines only, in proportion to their share of the order', function () {
    $own = Offer::factory()->create();
    $vendor = Vendor::factory()->active()->create();
    $vendorOffer = Offer::factory()->fromVendor($vendor->id)->create();

    // Own 60 000 + vendor 40 000 = 100 000 ; coupon 10 000 -> own share 6 000 -> 54 000 TTC -> 45 763 HT.
    marginSale('2026-10-10', [[$own, 1, 60_000, null, 20_000], [$vendorOffer, 1, 40_000, $vendor->id, null]], discount: 10_000);

    margin()->assertOk()
        ->assertJsonPath('totals.revenue', 45_763)
        ->assertJsonPath('totals.margin', 45_763 - 20_000);
});

it('only counts orders delivered within the period', function () {
    $offer = Offer::factory()->create();
    marginSale('2026-10-10', [[$offer, 1, 11_800, null, 4_000]]);
    marginSale('2026-09-30', [[$offer, 1, 99_000, null, 1_000]]);   // before
    marginSale('2026-11-01', [[$offer, 1, 99_000, null, 1_000]]);   // after
    $shipped = marginSale('2026-10-12', [[$offer, 1, 99_000, null, 1_000]]);
    $shipped->update(['status' => OrderStatus::Shipped]);               // not delivered

    margin()->assertOk()->assertJsonPath('totals.orders', 1)->assertJsonPath('totals.revenue', 10_000);
});

it('breaks the margin down by product, best margin first, and by day', function () {
    $a = Offer::factory()->create();
    $b = Offer::factory()->create();
    marginSale('2026-10-10', [[$a, 1, 11_800, null, 9_000], [$b, 1, 11_800, null, 2_000]]);   // a: 10 000−9 000 ; b: 10 000−2 000
    marginSale('2026-10-12', [[$b, 2, 11_800, null, 4_000]]);

    $response = margin()->assertOk();

    $response->assertJsonCount(2, 'by_product')
        ->assertJsonPath('by_product.0.offer_id', $b->id)        // b: 8 000 + 16 000 = 24 000
        ->assertJsonPath('by_product.0.units', 3)
        ->assertJsonPath('by_product.0.margin', 24_000)
        ->assertJsonPath('by_product.1.offer_id', $a->id)
        ->assertJsonPath('by_product.1.margin', 1_000)
        ->assertJsonCount(2, 'by_day')
        ->assertJsonPath('by_day.0.date', '2026-10-10')
        ->assertJsonPath('by_day.0.margin', 9_000)
        ->assertJsonPath('by_day.1.date', '2026-10-12');
});

it('warns when revenue has no cost on record, because the margin is then overstated', function () {
    $costed = Offer::factory()->create();
    $unknown = Offer::factory()->create();
    marginSale('2026-10-10', [[$costed, 1, 11_800, null, 4_000], [$unknown, 1, 11_800, null, null]]);

    margin()->assertOk()
        ->assertJsonPath('quality.uncosted_lines', 1)
        ->assertJsonPath('quality.uncosted_revenue', 10_000);
});

it('reports returns beside the margin without netting them', function () {
    $offer = Offer::factory()->create();
    $order = marginSale('2026-10-10', [[$offer, 3, 11_800, null, 12_000]]);
    ReturnRequest::factory()->status(ReturnStatus::Refunded)->create([
        'order_number' => $order->order_number, 'refunded_amount' => 11_800, 'refunded_at' => '2026-10-15 09:00:00',
    ]);
    CostReversal::query()->create(['return_id' => 1, 'order_id' => $order->id, 'amount' => 4_000])
        ->forceFill(['created_at' => '2026-10-15 09:00:00'])->save();

    margin()->assertOk()
        ->assertJsonPath('returns.refunded_ttc', 11_800)
        ->assertJsonPath('returns.cost_given_back', 4_000)
        // The margin itself is on what was delivered, untouched by the return.
        ->assertJsonPath('totals.cost', 12_000);
});

it('reports an empty period without dividing by zero', function () {
    margin('from=2026-01-01&to=2026-01-31')->assertOk()
        ->assertJsonPath('totals.revenue', 0)
        ->assertJsonPath('totals.margin', 0)
        ->assertJsonPath('totals.margin_percent', null)
        ->assertJsonPath('by_product', [])
        ->assertJsonPath('quality.uncosted_lines', 0);
});

it('records the cost line by line when an order is delivered, so the margin can be told per product', function () {
    $a = Offer::factory()->create();
    $b = Offer::factory()->create();
    OfferCost::query()->create(['offer_id' => $a->id, 'average_cost_amount' => 100]);
    OfferCost::query()->create(['offer_id' => $b->id, 'average_cost_amount' => 40]);

    $order = Order::factory()->create(['status' => OrderStatus::Shipped, 'subtotal_amount' => 4_000]);
    foreach ([[$a, 2, 1_000], [$b, 5, 400]] as [$offer, $qty, $price]) {
        OrderItem::factory()->create(['order_id' => $order->id, 'offer_id' => $offer->id, 'vendor_id' => null,
            'unit_price_amount' => $price, 'quantity' => $qty, 'line_total_amount' => $qty * $price]);
    }

    $ops = marginReader(Role::OperateurCommandes);
    $this->actingAs($ops, 'sanctum')->postJson("/api/v1/orders/{$order->order_number}/transition", ['status' => 'delivered'])->assertOk();

    $lines = OrderCostLine::query()->where('order_id', $order->id)->orderBy('offer_id')->get();
    expect($lines)->toHaveCount(2)
        ->and($lines->firstWhere('offer_id', $a->id)->cost_amount)->toBe(200)    // 2 × 100
        ->and($lines->firstWhere('offer_id', $b->id)->cost_amount)->toBe(200)    // 5 × 40
        ->and(OrderCost::query()->find($order->id)->cost_amount)->toBe(400);

    $this->actingAs(marginReader(), 'sanctum')
        ->getJson('/api/v1/admin/reports/margin?from='.now()->subDay()->toDateString().'&to='.now()->addDay()->toDateString())
        ->assertOk()->assertJsonPath('totals.cost', 400)->assertJsonCount(2, 'by_product');
});

it('exports the margin per product as a semicolon-separated CSV', function () {
    $offer = Offer::factory()->create();
    marginSale('2026-10-10', [[$offer, 3, 500, null, 300]]);

    $response = $this->actingAs(marginReader(), 'sanctum')
        ->get('/api/v1/admin/reports/margin.csv?from=2026-10-01&to=2026-10-31')
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $body = $response->streamedContent();
    expect($body)->toStartWith("\xEF\xBB\xBF")
        ->and($body)->toContain('produit;unites;chiffre_affaires_ht;cout;marge;marge_pourcent')
        ->and($body)->toContain(";3;1271;300;971;76.4");
    expect($response->headers->get('content-disposition'))->toContain('marge-20261001-20261031.csv');
});

it('rejects an inverted or malformed period like the other reports', function () {
    margin('from=2026-10-31&to=2026-10-01')->assertUnprocessable();
    margin('from=31/10/2026')->assertUnprocessable();
});
