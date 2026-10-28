<?php

declare(strict_types=1);

use App\Modules\Accounting\Infrastructure\Models\JournalEntry;
use App\Modules\Catalog\Domain\OfferStatus;
use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Notifications\Infrastructure\Models\Notification;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Payments\Application\RenderPaymentReceipt;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Vendors\Infrastructure\Models\Vendor;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function till(Role $role = Role::OperateurCommandes): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

function ownOffer(int $price = 50_000, int $stock = 5): Offer
{
    return Offer::factory()->create(['price_amount' => $price, 'stock_quantity' => $stock, 'status' => OfferStatus::Published, 'vendor_id' => null]);
}

function saleBody(Offer $offer, array $overrides = []): array
{
    return array_merge([
        'items' => [['offer_id' => $offer->id, 'quantity' => 2]],
        'method' => 'cash',
        'tendered_amount' => 150_000,
    ], $overrides);
}

it('restricts counter sales to the sales roles', function () {
    $offer = ownOffer();

    $this->postJson('/api/v1/admin/internal-sales', saleBody($offer))->assertUnauthorized();
    foreach ([Role::Support, Role::Logistique, Role::VendeurTiers, Role::Client] as $role) {
        $this->actingAs(till($role), 'sanctum')->postJson('/api/v1/admin/internal-sales', saleBody($offer))->assertForbidden();
    }
    $this->actingAs(till(Role::Comptable), 'sanctum')->postJson('/api/v1/admin/internal-sales', saleBody($offer))->assertCreated();
});

it('rings up a sale as a delivered, paid, invoiced order with a receipt', function () {
    $offer = ownOffer();

    $response = $this->actingAs(till(), 'sanctum')->postJson('/api/v1/admin/internal-sales', saleBody($offer, ['customer_name' => 'Awa Diallo']))
        ->assertCreated()
        ->assertJsonPath('status', 'delivered')
        ->assertJsonPath('customer_name', 'Awa Diallo')
        ->assertJsonPath('total_amount', 100_000)
        ->assertJsonPath('payment.method', 'cash')
        ->assertJsonPath('payment.amount_amount', 100_000)
        ->assertJsonPath('payment.tendered_amount', 150_000)
        ->assertJsonPath('payment.change_amount', 50_000);

    $order = Order::query()->where('order_number', $response->json('order_number'))->firstOrFail();
    expect($order->channel)->toBe('internal')->and($order->sold_by)->not->toBeNull()->and($order->payment_method)->toBe('cash')
        ->and($offer->fresh()->stock_quantity)->toBe(3)
        ->and(Invoice::query()->where('order_number', $order->order_number)->count())->toBe(1)
        ->and(Payment::query()->where('order_number', $order->order_number)->firstOrFail()->status->value)->toBe('completed')
        ->and(JournalEntry::query()->where('reference', $order->order_number)->count())->toBeGreaterThanOrEqual(2);
});

it('lets the walk-in stay anonymous and texts nobody', function () {
    $offer = ownOffer();

    $this->actingAs(till(), 'sanctum')->postJson('/api/v1/admin/internal-sales', saleBody($offer, ['customer_phone' => '0700000001']))
        ->assertCreated()->assertJsonPath('customer_name', 'Client comptoir');

    expect(Notification::query()->count())->toBe(0);
});

it('delivers a PDF receipt carrying the logo and the items', function () {
    $offer = ownOffer();
    $user = till();
    $number = $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/internal-sales', saleBody($offer))->json('order_number');

    $pdf = $this->actingAs($user, 'sanctum')->get("/api/v1/admin/internal-sales/{$number}/receipt")->assertOk();
    expect($pdf->headers->get('content-type'))->toBe('application/pdf')->and($pdf->getContent())->toStartWith('%PDF-');

    $payment = Payment::query()->where('order_number', $number)->firstOrFail();
    $html = app(RenderPaymentReceipt::class)->html($payment);
    expect($html)->toContain('data:image/png;base64,')->toContain('Espèces remises')->toContain('Monnaie rendue')->toContain('Qté');
});

it('refuses a receipt for an online order', function () {
    $online = Order::factory()->create();
    Payment::factory()->completed()->create(['order_number' => $online->order_number]);

    $this->actingAs(till(), 'sanctum')->getJson("/api/v1/admin/internal-sales/{$online->order_number}/receipt")->assertNotFound();
});

it('refuses insufficient cash and rolls everything back', function () {
    $offer = ownOffer();

    $this->actingAs(till(), 'sanctum')->postJson('/api/v1/admin/internal-sales', saleBody($offer, ['tendered_amount' => 90_000]))->assertUnprocessable();

    expect(Order::query()->count())->toBe(0)->and(Payment::query()->count())->toBe(0)->and($offer->fresh()->stock_quantity)->toBe(5);
});

it('refuses a third-party vendor offer and insufficient stock', function () {
    $vendorOffer = Offer::factory()->create(['price_amount' => 10_000, 'stock_quantity' => 5, 'status' => OfferStatus::Published, 'vendor_id' => Vendor::factory()->create()->id]);
    $scarce = ownOffer(stock: 1);

    $this->actingAs(till(), 'sanctum')->postJson('/api/v1/admin/internal-sales', saleBody($vendorOffer))->assertUnprocessable();
    $this->actingAs(till(), 'sanctum')->postJson('/api/v1/admin/internal-sales', saleBody($scarce))->assertUnprocessable();
    expect(Order::query()->count())->toBe(0);
});

it('requires an operator for mobile money and records the reference', function () {
    $offer = ownOffer();
    $user = till();

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/internal-sales', saleBody($offer, ['method' => 'mobile_money', 'tendered_amount' => null]))->assertUnprocessable();

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/internal-sales', saleBody($offer, ['method' => 'mobile_money', 'provider' => 'wave', 'provider_reference' => 'MM-123', 'tendered_amount' => null]))
        ->assertCreated()->assertJsonPath('payment.method', 'mobile_money')->assertJsonPath('payment.provider', 'wave')->assertJsonPath('payment.change_amount', null);
});

it('lists the counter sales with their receipt, never online orders', function () {
    $offer = ownOffer();
    $user = till();
    $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/internal-sales', saleBody($offer))->assertCreated();
    Order::factory()->create();

    $list = $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/internal-sales')->assertOk()->assertJsonCount(1, 'data');

    expect($list->json('data.0.payment.receipt_number'))->toStartWith('REC-');
});
