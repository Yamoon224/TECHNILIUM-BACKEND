<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\OfferStatus;
use App\Modules\Catalog\Domain\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\Category;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Catalog\Infrastructure\Models\ProductVariant;

it('lists active root categories with their children', function () {
    $phones = Category::factory()->create(['name' => 'Smartphones', 'slug' => 'smartphones', 'parent_id' => null]);
    Category::factory()->create(['name' => 'Android', 'slug' => 'android', 'parent_id' => $phones->id]);
    Category::factory()->create(['name' => 'Hidden', 'slug' => 'hidden', 'parent_id' => null, 'is_active' => false]);

    $response = $this->getJson('/api/v1/catalog/categories');

    $response->assertOk();
    $response->assertJsonCount(1); // only the one active root category
    $response->assertJsonPath('0.slug', 'smartphones');
    $response->assertJsonPath('0.children.0.slug', 'android');
});

it('lists only published products and surfaces the cheapest published offer', function () {
    $product = Product::factory()->create(['status' => ProductStatus::Published]);
    $variant = ProductVariant::factory()->for($product)->create();

    $variant->offers()->create([
        'price_amount' => 150_000,
        'currency' => 'XOF',
        'status' => OfferStatus::Published,
        'published_at' => now(),
    ]);
    $cheaper = $variant->offers()->create([
        'price_amount' => 120_000,
        'currency' => 'XOF',
        'status' => OfferStatus::Published,
        'published_at' => now(),
    ]);
    $variant->offers()->create([
        'price_amount' => 50_000, // cheapest, but not published -> must be ignored
        'currency' => 'XOF',
        'status' => OfferStatus::Draft,
    ]);

    Product::factory()->create(['status' => ProductStatus::Draft]); // must not appear

    $response = $this->getJson('/api/v1/catalog/products');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.slug', $product->slug);
    $response->assertJsonPath('data.0.best_offer.id', $cheaper->id);
    $response->assertJsonPath('data.0.best_offer.price.amount', 120_000);
});

it('never exposes non-published offers in the variants payload (no draft/pricing leak)', function () {
    $product = Product::factory()->create(['status' => ProductStatus::Published]);
    $variant = ProductVariant::factory()->for($product)->create();

    $variant->offers()->create([
        'price_amount' => 120_000,
        'currency' => 'XOF',
        'status' => OfferStatus::Published,
        'published_at' => now(),
    ]);
    $hidden = $variant->offers()->create([
        'price_amount' => 999_999,
        'currency' => 'XOF',
        'status' => OfferStatus::PendingReview,
    ]);

    $list = $this->getJson('/api/v1/catalog/products');
    $list->assertOk();
    $offerIds = collect($list->json('data.0.variants.0.offers'))->pluck('id');
    expect($offerIds)->not->toContain($hidden->id);

    $detail = $this->getJson("/api/v1/catalog/products/{$product->slug}");
    $detail->assertOk();
    $detailOfferIds = collect($detail->json('variants.0.offers'))->pluck('id');
    expect($detailOfferIds)->not->toContain($hidden->id);
});

it('filters products by category slug', function () {
    $match = Category::factory()->create(['slug' => 'ordinateurs']);
    $other = Category::factory()->create(['slug' => 'audio']);

    Product::factory()->for($match)->create(['status' => ProductStatus::Published]);
    Product::factory()->for($other)->create(['status' => ProductStatus::Published]);

    $response = $this->getJson('/api/v1/catalog/products?category=ordinateurs');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.category.slug', 'ordinateurs');
});

it('shows a product detail page by slug', function () {
    $product = Product::factory()->create(['status' => ProductStatus::Published, 'slug' => 'iphone-15-pro']);

    $this->getJson('/api/v1/catalog/products/iphone-15-pro')
        ->assertOk()
        ->assertJsonPath('slug', 'iphone-15-pro');
});

it('returns 404 for a draft product on the public detail endpoint', function () {
    Product::factory()->create(['status' => ProductStatus::Draft, 'slug' => 'not-yet-live']);

    $this->getJson('/api/v1/catalog/products/not-yet-live')->assertNotFound();
});

it('caps per_page at 100 and clamps a non-positive value to 1', function () {
    Product::factory()->create(['status' => ProductStatus::Published]);

    $this->getJson('/api/v1/catalog/products?per_page=999999')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);

    $this->getJson('/api/v1/catalog/products?per_page=-5')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 1);
});
