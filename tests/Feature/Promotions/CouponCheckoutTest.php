<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Promotions\Infrastructure\Models\Coupon;
use App\Modules\Promotions\Infrastructure\Models\CouponRedemption;
use App\Modules\Vendors\Infrastructure\Models\Vendor;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function checkoutWith(Offer $offer, ?string $coupon, string $phone = '0700000001', int $quantity = 1)
{
    return test()->postJson('/api/v1/orders', array_filter([
        'items' => [['offer_id' => $offer->id, 'quantity' => $quantity]],
        'customer_name' => 'Awa', 'customer_phone' => $phone, 'delivery_address' => 'Cocody',
        'coupon_code' => $coupon,
    ]));
}

function financeUser(Role $role = Role::Comptable): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

it('applies a coupon at checkout: the customer owes the net, the discount is recorded', function () {
    $offer = Offer::factory()->create(['price_amount' => 100_000, 'stock_quantity' => 5]);
    $coupon = Coupon::factory()->create(['code' => 'DIX', 'value' => 10]);

    checkoutWith($offer, 'dix')
        ->assertCreated()
        ->assertJsonPath('subtotal.amount', 90_000)
        ->assertJsonPath('discount.amount', 10_000)
        ->assertJsonPath('coupon_code', 'DIX');

    expect($coupon->fresh()->used_count)->toBe(1)
        ->and(CouponRedemption::query()->sole()->discount_amount)->toBe(10_000);
});

it('places an ordinary order untouched when no coupon is given', function () {
    $offer = Offer::factory()->create(['price_amount' => 100_000, 'stock_quantity' => 5]);

    checkoutWith($offer, null)
        ->assertCreated()
        ->assertJsonPath('subtotal.amount', 100_000)
        ->assertJsonPath('discount.amount', 0)
        ->assertJsonPath('coupon_code', null);
});

it('refuses the whole order when the coupon is invalid, leaving stock and counters alone', function () {
    $offer = Offer::factory()->create(['price_amount' => 100_000, 'stock_quantity' => 5]);

    checkoutWith($offer, 'FAUX')->assertUnprocessable();

    expect(Order::query()->count())->toBe(0)
        ->and($offer->fresh()->stock_quantity)->toBe(5);
});

it('stops a capped coupon at its last use', function () {
    $offer = Offer::factory()->create(['price_amount' => 10_000, 'stock_quantity' => 10]);
    Coupon::factory()->create(['code' => 'UNSEUL', 'max_uses' => 1]);

    checkoutWith($offer, 'UNSEUL', '0700000001')->assertCreated();
    checkoutWith($offer, 'UNSEUL', '0700000002')->assertUnprocessable();

    expect(Order::query()->count())->toBe(1);
});

it('limits a coupon per customer, by checkout phone', function () {
    $offer = Offer::factory()->create(['price_amount' => 10_000, 'stock_quantity' => 10]);
    Coupon::factory()->create(['code' => 'UNPARCLIENT', 'max_uses_per_customer' => 1]);

    checkoutWith($offer, 'UNPARCLIENT', '0700000001')->assertCreated();
    checkoutWith($offer, 'UNPARCLIENT', '0700000001')->assertUnprocessable();
    checkoutWith($offer, 'UNPARCLIENT', '0700000002')->assertCreated();
});

it('does not consume the coupon when the order itself fails (no stock)', function () {
    $offer = Offer::factory()->create(['price_amount' => 10_000, 'stock_quantity' => 1]);
    $coupon = Coupon::factory()->create(['code' => 'ROLLBACK', 'max_uses' => 1]);

    checkoutWith($offer, 'ROLLBACK', quantity: 5)->assertUnprocessable();

    expect($coupon->fresh()->used_count)->toBe(0)
        ->and(CouponRedemption::query()->count())->toBe(0);

    // ...so the single use is still available.
    checkoutWith($offer, 'ROLLBACK')->assertCreated();
});

it('invoices the net amount with VAT on what was paid, and shows the discount', function () {
    $offer = Offer::factory()->create(['price_amount' => 118_000, 'stock_quantity' => 5]);
    Coupon::factory()->create(['code' => 'VINGT', 'value' => 20]);

    $orderNumber = checkoutWith($offer, 'VINGT')->assertCreated()->json('order_number');
    $order = Order::query()->where('order_number', $orderNumber)->firstOrFail();
    $order->update(['status' => OrderStatus::Confirmed]);

    $invoice = test()->actingAs(financeUser(), 'sanctum')
        ->postJson('/api/v1/admin/invoices', ['order_number' => $order->order_number])
        ->assertCreated();

    // Paid 94 400 TTC: net 80 000 + VAT 14 400, discount 23 600 shown, coupon named.
    $invoice->assertJsonPath('total_amount', 94_400)
        ->assertJsonPath('net_amount', 80_000)
        ->assertJsonPath('vat_amount', 14_400)
        ->assertJsonPath('discount_amount', 23_600)
        ->assertJsonPath('coupon_code', 'VINGT');
});

it('keeps the vendor paid on the list price: the discount is Technilium\'s cost', function () {
    $vendor = Vendor::factory()->active()->create();
    $offer = Offer::factory()->fromVendor($vendor->id)->create(['price_amount' => 100_000, 'stock_quantity' => 5]);
    Coupon::factory()->create(['code' => 'DIX', 'value' => 10]);

    $orderNumber = checkoutWith($offer, 'DIX')->assertCreated()->json('order_number');
    $order = Order::query()->where('order_number', $orderNumber)->firstOrFail();
    $order->subOrders()->update(['status' => OrderStatus::Delivered, 'delivered_at' => now()->subDays(10)]);
    $order->update(['status' => OrderStatus::Delivered, 'delivered_at' => now()->subDays(10)]);

    // Customer paid 90 000, but the vendor's gross is the 100 000 list price.
    test()->actingAs(financeUser(), 'sanctum')
        ->postJson('/api/v1/admin/payouts', ['vendor_id' => $vendor->id])
        ->assertCreated()
        ->assertJsonPath('gross_amount', 100_000)
        ->assertJsonPath('commission_amount', 10_000)
        ->assertJsonPath('net_amount', 90_000);
});

it('refunds returned articles in proportion to what was actually paid', function () {
    $offer = Offer::factory()->create(['price_amount' => 100_000, 'stock_quantity' => 5]);
    Coupon::factory()->create(['code' => 'DIX', 'value' => 10]);

    $orderNumber = checkoutWith($offer, 'DIX')->assertCreated()->json('order_number');
    Order::query()->where('order_number', $orderNumber)
        ->update(['status' => OrderStatus::Delivered, 'delivered_at' => now()->subDay()]);

    // The article cost 100 000 at list price but 90 000 once discounted.
    test()->actingAs(financeUser(Role::Administrateur), 'sanctum')
        ->postJson('/api/v1/admin/returns', [
            'order_number' => $orderNumber, 'reason' => 'Défaut',
            'items' => [['offer_id' => $offer->id, 'quantity' => 1]],
        ])
        ->assertCreated()
        ->assertJsonPath('requested_amount', 90_000);
});

it('shows the discount to the customer on lookup', function () {
    $offer = Offer::factory()->create(['price_amount' => 100_000, 'stock_quantity' => 5]);
    Coupon::factory()->create(['code' => 'DIX', 'value' => 10]);

    $orderNumber = checkoutWith($offer, 'DIX', '0700000009')->json('order_number');

    test()->getJson("/api/v1/orders/{$orderNumber}?phone=0700000009")
        ->assertOk()
        ->assertJsonPath('discount.amount', 10_000)
        ->assertJsonPath('subtotal.amount', 90_000);
});
