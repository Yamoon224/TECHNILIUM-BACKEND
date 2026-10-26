<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\OfferStatus;
use App\Modules\Catalog\Infrastructure\Models\Category;
use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Catalog\Infrastructure\Models\ProductVariant;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function anotherCatalogAdminUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::GestionnaireCatalogue->value);

    return $user;
}

it('rejects an anonymous request to list admin products', function () {
    $this->getJson('/api/v1/admin/products')->assertUnauthorized();
});

it('rejects a client-role user from listing admin products', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::Client->value);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/admin/products')
        ->assertForbidden();
});

it('lists products of every status for a catalog admin', function () {
    Product::factory()->count(2)->create();
    Product::factory()->draft()->create();

    $this->actingAs(anotherCatalogAdminUser(), 'sanctum')
        ->getJson('/api/v1/admin/products')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('caps per_page and clamps a non-positive value to 1', function () {
    Product::factory()->count(3)->create();

    $this->actingAs(anotherCatalogAdminUser(), 'sanctum')
        ->getJson('/api/v1/admin/products?per_page=999999')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);

    $this->actingAs(anotherCatalogAdminUser(), 'sanctum')
        ->getJson('/api/v1/admin/products?per_page=-5')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 1);
});

it('creates a simple product with one variant and one published offer', function () {
    $category = Category::factory()->create();

    $response = $this->actingAs(anotherCatalogAdminUser(), 'sanctum')
        ->postJson('/api/v1/admin/products', [
            'category_id' => $category->id,
            'brand' => 'Samsung',
            'name' => 'Galaxy A15',
            'price_amount' => 85000,
            'stock_quantity' => 10,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('brand', 'Samsung');
    $response->assertJsonPath('status', 'published');
    $response->assertJsonPath('price_amount', 85000);
    $response->assertJsonPath('stock_quantity', 10);
    expect($response->json('sku'))->not->toBeNull();
});

it('rejects a product referencing an unknown category', function () {
    $this->actingAs(anotherCatalogAdminUser(), 'sanctum')
        ->postJson('/api/v1/admin/products', [
            'category_id' => 999999,
            'brand' => 'Samsung',
            'name' => 'Galaxy A15',
            'price_amount' => 85000,
            'stock_quantity' => 10,
        ])
        ->assertUnprocessable();
});

it('updates a product and its offer price and stock', function () {
    $product = Product::factory()
        ->has(
            ProductVariant::factory()
                ->has(Offer::factory()->state([
                    'price_amount' => 10000,
                    'stock_quantity' => 5,
                ]), 'offers'),
            'variants',
        )
        ->create();

    $response = $this->actingAs(anotherCatalogAdminUser(), 'sanctum')
        ->putJson("/api/v1/admin/products/{$product->id}", [
            'price_amount' => 12000,
            'stock_quantity' => 3,
        ]);

    $response->assertOk();
    $response->assertJsonPath('price_amount', 12000);
    $response->assertJsonPath('stock_quantity', 3);
});

it('archives a product and its offers on destroy', function () {
    $product = Product::factory()
        ->has(
            ProductVariant::factory()
                ->has(Offer::factory(), 'offers'),
            'variants',
        )
        ->create();

    $response = $this->actingAs(anotherCatalogAdminUser(), 'sanctum')
        ->deleteJson("/api/v1/admin/products/{$product->id}");

    $response->assertOk();
    $response->assertJsonPath('status', 'archived');
    expect($product->variants->first()->offers->first()->fresh()->status)
        ->toBe(OfferStatus::Archived);
});
