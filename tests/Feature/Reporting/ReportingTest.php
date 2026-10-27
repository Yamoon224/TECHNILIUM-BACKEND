<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use App\Modules\Orders\Infrastructure\Models\SubOrder;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Payments\Infrastructure\Models\PaymentRefund;
use App\Modules\Payouts\Domain\PayoutStatus;
use App\Modules\Payouts\Infrastructure\Models\Payout;
use App\Modules\Returns\Domain\ReturnStatus;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use App\Modules\Vendors\Infrastructure\Models\Vendor;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function reader(Role $role = Role::Administrateur): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

function saleOn(string $day, int $total, OrderStatus $status = OrderStatus::Confirmed, ?int $vendorId = null, string $product = 'Galaxy A15'): Order
{
    $order = Order::factory()->create([
        'status' => $status, 'subtotal_amount' => $total, 'created_at' => $day.' 10:00:00',
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id, 'vendor_id' => $vendorId, 'brand' => 'Samsung', 'product_name' => $product,
        'unit_price_amount' => $total, 'quantity' => 1, 'line_total_amount' => $total,
    ]);
    SubOrder::factory()->create([
        'order_id' => $order->id, 'vendor_id' => $vendorId, 'status' => $status,
        'subtotal_amount' => $total, 'created_at' => $day.' 10:00:00',
    ]);

    return $order;
}

it('restricts reports to administrators and accountants', function () {
    $this->getJson('/api/v1/admin/reports/dashboard')->assertUnauthorized();

    foreach ([Role::Support, Role::OperateurCommandes, Role::VendeurTiers, Role::Marketing] as $role) {
        $this->actingAs(reader($role), 'sanctum')->getJson('/api/v1/admin/reports/dashboard')->assertForbidden();
    }

    $this->actingAs(reader(Role::Comptable), 'sanctum')->getJson('/api/v1/admin/reports/dashboard')->assertOk();
});

it('computes revenue, order count and average basket, ignoring cancelled orders', function () {
    saleOn('2026-10-10', 100_000);
    saleOn('2026-10-10', 50_000);
    saleOn('2026-10-12', 30_000);
    saleOn('2026-10-12', 999_000, OrderStatus::Cancelled);
    saleOn('2026-09-01', 777_000); // outside the period

    $response = $this->actingAs(reader(), 'sanctum')
        ->getJson('/api/v1/admin/reports/dashboard?from=2026-10-01&to=2026-10-31')
        ->assertOk();

    $response->assertJsonPath('sales.revenue', 180_000)
        ->assertJsonPath('sales.orders', 3)
        ->assertJsonPath('sales.average_basket', 60_000)
        ->assertJsonPath('sales.by_day.0.date', '2026-10-10')
        ->assertJsonPath('sales.by_day.0.revenue', 150_000)
        ->assertJsonPath('sales.by_day.1.orders', 1)
        ->assertJsonPath('orders_by_status.cancelled', 1)
        ->assertJsonPath('orders_by_status.confirmed', 3);
});

it('ranks top products by revenue', function () {
    saleOn('2026-10-10', 100_000, product: 'iPhone 13');
    saleOn('2026-10-11', 40_000, product: 'Galaxy A15');
    saleOn('2026-10-12', 30_000, product: 'Galaxy A15');

    $this->actingAs(reader(), 'sanctum')
        ->getJson('/api/v1/admin/reports/dashboard?from=2026-10-01&to=2026-10-31')
        ->assertJsonPath('top_products.0.product', 'Samsung iPhone 13')
        ->assertJsonPath('top_products.0.revenue', 100_000)
        ->assertJsonPath('top_products.1.product', 'Samsung Galaxy A15')
        ->assertJsonPath('top_products.1.quantity', 2)
        ->assertJsonPath('top_products.1.revenue', 70_000);
});

it('reports an empty period without dividing by zero', function () {
    $this->actingAs(reader(), 'sanctum')
        ->getJson('/api/v1/admin/reports/dashboard?from=2026-01-01&to=2026-01-31')
        ->assertOk()
        ->assertJsonPath('sales.revenue', 0)
        ->assertJsonPath('sales.average_basket', 0)
        ->assertJsonPath('returns.rate_percent', 0)
        ->assertJsonPath('sales.by_day', []);
});

it('reports collections, refunds, commissions and what is owed to vendors', function () {
    Payment::factory()->completed()->create(['amount_amount' => 100_000, 'method' => 'cod', 'paid_at' => '2026-10-10 12:00:00']);
    Payment::factory()->completed()->create(['amount_amount' => 50_000, 'method' => 'mobile_money', 'provider' => 'wave', 'paid_at' => '2026-10-11 12:00:00']);
    Payment::factory()->create(['amount_amount' => 999_000]); // pending: not collected
    $refunded = Payment::factory()->completed()->create(['amount_amount' => 10_000, 'paid_at' => '2026-09-01 12:00:00']);
    PaymentRefund::query()->create(['payment_id' => $refunded->id, 'amount' => 4_000])
        ->forceFill(['created_at' => '2026-10-15 09:00:00'])->save();

    Payout::factory()->create(['commission_amount' => 6_000, 'net_amount' => 54_000, 'created_at' => '2026-10-12 09:00:00']);
    Payout::factory()->create([
        'status' => PayoutStatus::Paid, 'commission_amount' => 1_000, 'net_amount' => 9_000,
        'created_at' => '2026-10-13 09:00:00', 'paid_at' => '2026-10-14 09:00:00',
    ]);

    $this->actingAs(reader(), 'sanctum')
        ->getJson('/api/v1/admin/reports/dashboard?from=2026-10-01&to=2026-10-31')
        ->assertJsonPath('finance.collected', 150_000)
        ->assertJsonPath('finance.collected_by_method.cod', 100_000)
        ->assertJsonPath('finance.collected_by_method.mobile_money', 50_000)
        ->assertJsonPath('finance.refunded', 4_000)
        ->assertJsonPath('finance.commissions', 7_000)
        ->assertJsonPath('finance.paid_to_vendors', 9_000)
        ->assertJsonPath('finance.owed_to_vendors', 54_000);
});

it('reports returns and the return rate', function () {
    saleOn('2026-10-10', 100_000);
    saleOn('2026-10-11', 100_000);
    saleOn('2026-10-12', 100_000);
    saleOn('2026-10-13', 100_000);
    ReturnRequest::factory()->status(ReturnStatus::Refunded)->create([
        'refunded_amount' => 25_000, 'refunded_at' => '2026-10-20 09:00:00', 'created_at' => '2026-10-15 09:00:00',
    ]);

    $this->actingAs(reader(), 'sanctum')
        ->getJson('/api/v1/admin/reports/dashboard?from=2026-10-01&to=2026-10-31')
        ->assertJsonPath('returns.count', 1)
        ->assertJsonPath('returns.by_status.refunded', 1)
        ->assertJsonPath('returns.rate_percent', 25)
        ->assertJsonPath('returns.refunded_amount', 25_000);
});

it('raises alerts for stalled orders and low stock', function () {
    Order::factory()->create(['status' => OrderStatus::Pending, 'created_at' => now()->subDays(10)]);
    Order::factory()->create(['status' => OrderStatus::Confirmed, 'created_at' => now()->subDays(5)]);
    Order::factory()->create(['status' => OrderStatus::Pending, 'created_at' => now()->subHour()]);
    Order::factory()->create(['status' => OrderStatus::Delivered, 'created_at' => now()->subDays(20)]);
    Offer::factory()->create(['stock_quantity' => 0]);
    Offer::factory()->create(['stock_quantity' => 3]);
    Offer::factory()->create(['stock_quantity' => 50]);

    $this->actingAs(reader(), 'sanctum')
        ->getJson('/api/v1/admin/reports/dashboard')
        ->assertJsonPath('alerts.stalled_orders', 2)
        ->assertJsonPath('alerts.low_stock_count', 2)
        ->assertJsonPath('alerts.low_stock.0.stock', 0);
});

it('names sellers in the sales-by-seller report, Technilium included', function () {
    $vendor = Vendor::factory()->active()->create(['company_name' => 'TechShop Abidjan']);
    saleOn('2026-10-10', 80_000, vendorId: $vendor->id);
    saleOn('2026-10-11', 20_000);

    $response = $this->actingAs(reader(), 'sanctum')
        ->getJson('/api/v1/admin/reports/sales-by-seller?from=2026-10-01&to=2026-10-31')
        ->assertOk();

    $response->assertJsonPath('data.0.seller', 'TechShop Abidjan')
        ->assertJsonPath('data.0.revenue', 80_000)
        ->assertJsonPath('data.1.seller', 'Technilium')
        ->assertJsonPath('data.1.vendor_id', null);
});

it('exports daily sales as a semicolon-separated CSV', function () {
    saleOn('2026-10-10', 100_000);
    saleOn('2026-10-10', 50_000);

    $response = $this->actingAs(reader(), 'sanctum')
        ->get('/api/v1/admin/reports/sales.csv?from=2026-10-01&to=2026-10-31')
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $body = $response->streamedContent();
    expect($body)->toStartWith("\xEF\xBB\xBF")
        ->and($body)->toContain("date;commandes;chiffre_affaires")
        ->and($body)->toContain('2026-10-10;2;150000');
    expect($response->headers->get('content-disposition'))->toContain('ventes-20261001-20261031.csv');
});

it('rejects an inverted, malformed or overly long period', function (string $query) {
    $this->actingAs(reader(), 'sanctum')
        ->getJson("/api/v1/admin/reports/dashboard?{$query}")
        ->assertUnprocessable();
})->with([
    'inverted' => 'from=2026-10-31&to=2026-10-01',
    'malformed' => 'from=31/10/2026',
    'too long' => 'from=2024-01-01&to=2026-10-31',
]);
