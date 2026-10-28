<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Catalog\Infrastructure\Models\ProductVariant;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Procurement\Infrastructure\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function qrUser(Role $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);
    Sanctum::actingAs($user);

    return $user;
}

it('requires the QR feature', function () {
    $this->getJson('/api/v1/admin/qrcodes/payload?kind=order&reference=X')->assertUnauthorized();

    foreach ([Role::Client, Role::Support, Role::Logistique] as $role) {
        qrUser($role);
        $this->getJson('/api/v1/admin/qrcodes/payload?kind=order&reference=X')->assertForbidden();
    }
});

it('identifies a product by its SKU, an order, an invoice and a supply', function () {
    $product = Product::factory()->create(['name' => 'Écran 27 pouces']);
    ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'MON-27-BK']);
    $order = Order::factory()->create();
    $invoice = Invoice::factory()->create();
    $supplier = Supplier::factory()->create(['name' => 'Dakar Import']);
    $supplyId = DB::table('purchase_orders')->insertGetId(['supplier_id' => $supplier->id, 'status' => 'draft', 'currency' => 'XOF', 'created_at' => now(), 'updated_at' => now()]);
    qrUser(Role::Administrateur);

    $this->getJson('/api/v1/admin/qrcodes/payload?kind=product&reference=MON-27-BK')->assertOk()
        ->assertJsonPath('payload', 'TECHNILIUM:PRODUCT:MON-27-BK')->assertJsonPath('caption', 'Écran 27 pouces — MON-27-BK');
    $this->getJson("/api/v1/admin/qrcodes/payload?kind=order&reference={$order->order_number}")->assertOk()
        ->assertJsonPath('payload', "TECHNILIUM:ORDER:{$order->order_number}");
    $this->getJson("/api/v1/admin/qrcodes/payload?kind=invoice&reference={$invoice->number}")->assertOk()
        ->assertJsonPath('payload', "TECHNILIUM:INVOICE:{$invoice->number}");
    $this->getJson("/api/v1/admin/qrcodes/payload?kind=supply&reference={$supplyId}")->assertOk()
        ->assertJsonPath('payload', "TECHNILIUM:SUPPLY:{$supplyId}")->assertJsonPath('caption', "Approvisionnement n°{$supplyId} — Dakar Import");
});

it('answers 404 for a reference that does not exist', function () {
    qrUser(Role::Administrateur);

    foreach (['product', 'order', 'invoice', 'supply'] as $kind) {
        $this->getJson("/api/v1/admin/qrcodes/payload?kind={$kind}&reference=123456")->assertNotFound();
    }
});

it('also needs the ability that opens that kind of record', function () {
    $order = Order::factory()->create();
    $invoice = Invoice::factory()->create();
    qrUser(Role::Comptable);

    $this->getJson("/api/v1/admin/qrcodes/payload?kind=invoice&reference={$invoice->number}")->assertOk();
    $this->getJson("/api/v1/admin/qrcodes/payload?kind=order&reference={$order->order_number}")->assertForbidden();
});

it('validates the kind and the reference', function () {
    qrUser(Role::Administrateur);

    $this->getJson('/api/v1/admin/qrcodes/payload?kind=autre&reference=X')->assertUnprocessable();
    $this->getJson('/api/v1/admin/qrcodes/payload?kind=order')->assertUnprocessable();
    $this->getJson('/api/v1/admin/qrcodes/payload?kind=order&reference='.urlencode('<script>'))->assertUnprocessable();
});

it('disappears for a group the super admin switched it off for', function () {
    $user = qrUser(Role::Administrateur);
    $this->getJson('/api/v1/admin/qrcodes/payload?kind=order&reference=X')->assertNotFound();

    DB::table('feature_overrides')->insert(['feature' => 'generate-qrcodes', 'subject_type' => 'user', 'subject' => (string) $user->id, 'enabled' => false, 'created_at' => now(), 'updated_at' => now()]);
    app(App\Modules\Identity\Application\AccessResolver::class)->forget();

    $this->getJson('/api/v1/admin/qrcodes/payload?kind=order&reference=X')->assertForbidden();
});
