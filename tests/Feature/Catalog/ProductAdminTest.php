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

function photo(string $name = 'a.jpg', int $size = 400): Illuminate\Http\UploadedFile
{
    return Illuminate\Http\UploadedFile::fake()->image($name, $size, $size);
}

function addPhoto(Product $product, ?Illuminate\Http\UploadedFile $file = null, ?User $by = null): Illuminate\Testing\TestResponse
{
    return test()->actingAs($by ?? anotherCatalogAdminUser(), 'sanctum')
        ->post("/api/v1/admin/products/{$product->id}/images", ['image' => $file ?? photo()], ['Accept' => 'application/json']);
}

it('builds a gallery: the first photo is the cover, the next ones follow', function () {
    Illuminate\Support\Facades\Storage::fake('public');
    $product = Product::factory()->create();

    $first = addPhoto($product)->assertOk();
    addPhoto($product, photo('b.png'))->assertOk();
    $third = addPhoto($product, photo('c.webp'))->assertOk();

    $images = $third->json('images');
    expect($images)->toHaveCount(3)
        ->and($third->json('image_url'))->toBe($images[0]['url'])
        ->and($first->json('images.0.id'))->toBe($images[0]['id'])
        ->and($product->images()->pluck('position')->all())->toBe([0, 1, 2]);

    foreach ($product->images as $image) {
        Illuminate\Support\Facades\Storage::disk('public')->assertExists($image->path);
    }
});

it('stops at eight photos', function () {
    Illuminate\Support\Facades\Storage::fake('public');
    $product = Product::factory()->create();

    foreach (range(1, 8) as $i) {
        addPhoto($product)->assertOk();
    }

    addPhoto($product)->assertUnprocessable()->assertJsonValidationErrors('image');
    expect($product->images()->count())->toBe(8);
});

it('moves a chosen photo to the cover, keeping the others in order', function () {
    Illuminate\Support\Facades\Storage::fake('public');
    $product = Product::factory()->create();
    foreach (range(1, 3) as $i) {
        addPhoto($product);
    }
    [$a, $b, $c] = $product->images()->pluck('id')->all();

    $this->actingAs(anotherCatalogAdminUser(), 'sanctum')
        ->postJson("/api/v1/admin/products/{$product->id}/images/{$c}/cover")
        ->assertOk()
        ->assertJsonPath('images.0.id', $c);

    expect($product->images()->pluck('id')->all())->toBe([$c, $a, $b]);
});

it('removes a photo and its file, and the next one becomes the cover', function () {
    Illuminate\Support\Facades\Storage::fake('public');
    $product = Product::factory()->create();
    addPhoto($product);
    addPhoto($product);
    [$cover, $second] = $product->images()->get()->all();

    $this->actingAs(anotherCatalogAdminUser(), 'sanctum')
        ->deleteJson("/api/v1/admin/products/{$product->id}/images/{$cover->id}")
        ->assertOk()
        ->assertJsonPath('images.0.id', $second->id);

    Illuminate\Support\Facades\Storage::disk('public')->assertMissing($cover->path);
    Illuminate\Support\Facades\Storage::disk('public')->assertExists($second->path);
});

it('cannot touch another product\'s photo through the wrong product id', function () {
    Illuminate\Support\Facades\Storage::fake('public');
    $mine = Product::factory()->create();
    $other = Product::factory()->create();
    addPhoto($other);
    $theirs = $other->images()->sole();
    $admin = anotherCatalogAdminUser();

    $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/admin/products/{$mine->id}/images/{$theirs->id}")->assertNotFound();
    $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/products/{$mine->id}/images/{$theirs->id}/cover")->assertNotFound();
    expect($other->images()->count())->toBe(1);
});

it('refuses a non-image, an SVG, an oversized or a tiny file', function () {
    Illuminate\Support\Facades\Storage::fake('public');
    $product = Product::factory()->create();

    foreach ([
        Illuminate\Http\UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        Illuminate\Http\UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        photo('big.jpg')->size(3000),
        photo('tiny.jpg', 50),
    ] as $file) {
        addPhoto($product, $file)->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    expect($product->images()->count())->toBe(0);
});

it('restricts gallery changes to catalogue staff', function () {
    $product = Product::factory()->create();
    $client = User::factory()->create();
    $client->assignRole(Role::Client->value);

    $this->postJson("/api/v1/admin/products/{$product->id}/images")->assertUnauthorized();
    $this->actingAs($client, 'sanctum')->postJson("/api/v1/admin/products/{$product->id}/images")->assertForbidden();
    $this->actingAs($client, 'sanctum')->deleteJson("/api/v1/admin/products/{$product->id}/images/1")->assertForbidden();
    $this->actingAs($client, 'sanctum')->postJson("/api/v1/admin/products/{$product->id}/images/1/cover")->assertForbidden();
});

it('serves the gallery on the public catalogue, empty when there is none', function () {
    Illuminate\Support\Facades\Storage::fake('public');
    $with = Product::factory()->create(['status' => 'published']);
    $without = Product::factory()->create(['status' => 'published']);
    addPhoto($with);
    addPhoto($with);

    $this->getJson("/api/v1/catalog/products/{$with->slug}")->assertOk()
        ->assertJsonCount(2, 'images')
        ->assertJsonPath('image_url', fn ($url) => is_string($url) && str_contains($url, 'products/'));
    $this->getJson("/api/v1/catalog/products/{$without->slug}")->assertOk()
        ->assertJsonCount(0, 'images')->assertJsonPath('image_url', null);

    $list = $this->getJson('/api/v1/catalog/products')->assertOk()->json('data');
    expect(collect($list)->firstWhere('id', $with->id)['image_url'])->toBeString();
});
