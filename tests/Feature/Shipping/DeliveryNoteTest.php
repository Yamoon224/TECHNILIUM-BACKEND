<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use App\Modules\Shipping\Application\RenderDeliveryNote;
use App\Modules\Shipping\Infrastructure\Models\Shipment;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function noteStaff(Role $role = Role::Logistique): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

/** A shipment of an order with two articles priced 59 000. */
function noteShipment(array $overrides = []): Shipment
{
    $order = Order::factory()->create(['status' => OrderStatus::Confirmed, 'delivery_code' => '482913']);
    OrderItem::factory()->create(['order_id' => $order->id, 'brand' => 'Samsung', 'product_name' => 'Galaxy A15', 'quantity' => 2, 'unit_price_amount' => 59_000, 'line_total_amount' => 118_000]);

    return Shipment::factory()->create($overrides + [
        'order_number' => $order->order_number,
        'customer_name' => 'Awa Diallo', 'customer_phone' => '0700000001',
        'delivery_address' => 'Cocody Angré, près de la pharmacie', 'delivery_city' => 'Abidjan',
    ]);
}

it('restricts the delivery note to logistics staff', function () {
    $shipment = noteShipment();

    $this->getJson("/api/v1/admin/shipments/{$shipment->id}/delivery-note")->assertUnauthorized();

    foreach ([Role::Support, Role::Comptable, Role::VendeurTiers, Role::Client] as $role) {
        $this->actingAs(noteStaff($role), 'sanctum')->get("/api/v1/admin/shipments/{$shipment->id}/delivery-note")->assertForbidden();
    }
});

it('downloads a real PDF named after the shipment', function () {
    $shipment = noteShipment();

    $response = $this->actingAs(noteStaff(), 'sanctum')->get("/api/v1/admin/shipments/{$shipment->id}/delivery-note")->assertOk();

    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain(sprintf('BL-%06d.pdf', $shipment->id))
        ->and($response->getContent())->toStartWith('%PDF-');
});

it('answers 404 for an unknown shipment', function () {
    $this->actingAs(noteStaff(), 'sanctum')->get('/api/v1/admin/shipments/999999/delivery-note')->assertNotFound();
});

it('names the recipient, the address and the articles to hand over', function () {
    $page = app(RenderDeliveryNote::class)->html(noteShipment());

    expect($page)->toContain('BON DE LIVRAISON')->toContain('Awa Diallo')->toContain('0700000001')
        ->toContain('Cocody Angré')->toContain('Abidjan')->toContain('Samsung Galaxy A15')
        ->toContain('Signature du client')->toContain('Signature du livreur');
});

it('never prints a price or the delivery confirmation code on the courier paper', function () {
    $page = app(RenderDeliveryNote::class)->html(noteShipment());

    expect($page)->not->toContain('482913')
        ->and($page)->not->toContain('59')            // unit price 59 000
        ->and($page)->not->toContain('118')           // line total
        ->and($page)->not->toContain('F CFA');
});

it('renders in English on request, and falls back to French otherwise', function () {
    $shipment = noteShipment();

    expect(app(RenderDeliveryNote::class)->html($shipment, 'en'))->toContain('DELIVERY NOTE')->toContain('Customer signature')->not->toContain('BON DE LIVRAISON')
        ->and(app(RenderDeliveryNote::class)->html($shipment, 'de'))->toContain('BON DE LIVRAISON');
});

it('escapes hostile text in the address and notes', function () {
    $page = app(RenderDeliveryNote::class)->html(noteShipment([
        'customer_name' => '<script>alert(1)</script>', 'notes' => '<img src="http://evil.example/x.png">',
    ]));

    expect($page)->not->toContain('<script>')->not->toContain('<img src=')->toContain('&lt;script&gt;');
});
