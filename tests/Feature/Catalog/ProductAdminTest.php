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

it('uploads a product photo, exposes its URL publicly and replaces the previous file', function () {
    Illuminate\Support\Facades\Storage::fake('public');
    $product = Product::factory()->create();

    $first = $this->actingAs(anotherCatalogAdminUser(), 'sanctum')
        ->post("/api/v1/admin/products/{$product->id}/image", ['image' => Illuminate\Http\UploadedFile::fake()->image('a.jpg', 400, 400)], ['Accept' => 'application/json'])
        ->assertOk();

    $firstPath = $product->fresh()->image_path;
    expect($firstPath)->toStartWith('products/')->and($first->json('image_url'))->toContain($firstPath);
    Illuminate\Support\Facades\Storage::disk('public')->assertExists($firstPath);

    $this->actingAs(anotherCatalogAdminUser(), 'sanctum')
        ->post("/api/v1/admin/products/{$product->id}/image", ['image' => Illuminate\Http\UploadedFile::fake()->image('b.png', 400, 400)], ['Accept' => 'application/json'])
        ->assertOk();

    Illuminate\Support\Facades\Storage::disk('public')->assertMissing($firstPath);
    expect($product->fresh()->image_path)->not->toBe($firstPath);
});

it('refuses a non-image, an SVG, an oversized or a tiny file', function () {
    Illuminate\Support\Facades\Storage::fake('public');
    $product = Product::factory()->create();
    $admin = anotherCatalogAdminUser();

    foreach ([
        Illuminate\Http\UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        Illuminate\Http\UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        Illuminate\Http\UploadedFile::fake()->image('big.jpg', 400, 400)->size(3000),
        Illuminate\Http\UploadedFile::fake()->image('tiny.jpg', 50, 50),
    ] as $file) {
        $this->actingAs($admin, 'sanctum')
            ->post("/api/v1/admin/products/{$product->id}/image", ['image' => $file], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    expect($product->fresh()->image_path)->toBeNull();
});

it('restricts photo changes to catalogue staff', function () {
    $product = Product::factory()->create();
    $client = User::factory()->create();
    $client->assignRole(Role::Client->value);

    $this->postJson("/api/v1/admin/products/{$product->id}/image")->assertUnauthorized();
    $this->actingAs($client, 'sanctum')->postJson("/api/v1/admin/products/{$product->id}/image")->assertForbidden();
    $this->actingAs($client, 'sanctum')->deleteJson("/api/v1/admin/products/{$product->id}/image")->assertForbidden();
});

it('removes a photo and its file', function () {
    Illuminate\Support\Facades\Storage::fake('public');
    $product = Product::factory()->create();
    $admin = anotherCatalogAdminUser();
    $this->actingAs($admin, 'sanctum')
        ->post("/api/v1/admin/products/{$product->id}/image", ['image' => Illuminate\Http\UploadedFile::fake()->image('a.jpg', 400, 400)], ['Accept' => 'application/json']);
    $path = $product->fresh()->image_path;

    $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/admin/products/{$product->id}/image")
        ->assertOk()->assertJsonPath('image_url', null);

    Illuminate\Support\Facades\Storage::disk('public')->assertMissing($path);
});

it('serves the photo URL on the public catalogue, null when there is none', function () {
    Illuminate\Support\Facades\Storage::fake('public');
    $with = Product::factory()->create(['image_path' => 'products/x.jpg']);
    $without = Product::factory()->create();

    $this->getJson("/api/v1/catalog/products/{$with->slug}")->assertOk()->assertJsonPath('image_url', fn ($url) => str_ends_with($url, 'products/x.jpg'));
    $this->getJson("/api/v1/catalog/products/{$without->slug}")->assertOk()->assertJsonPath('image_url', null);
});
