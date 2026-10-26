<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\OfferStatus;
use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Catalog\Infrastructure\Models\ProductVariant;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Vendors\Infrastructure\Models\Vendor;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function activeVendorUser(): array
{
    $user = User::factory()->create();
    $user->assignRole(Role::VendeurTiers->value);
    $vendor = Vendor::factory()->active()->create(['user_id' => $user->id]);

    return [$user, $vendor];
}

it('rejects an anonymous request to list own offers', function () {
    $this->getJson('/api/v1/vendors/me/offers')->assertUnauthorized();
});

it('rejects a non-vendor role from the vendor offer endpoints', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::Client->value);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/vendors/me/offers')
        ->assertForbidden();
});

it('rejects a vendor whose profile is still pending', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::VendeurTiers->value);
    Vendor::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/vendors/me/offers')
        ->assertStatus(403);
});

it('rejects a suspended vendor from submitting offers', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::VendeurTiers->value);
    Vendor::factory()->suspended()->create(['user_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/vendors/me/offers')
        ->assertStatus(403);
});

it('submits a new offer pending moderation', function () {
    [$user] = activeVendorUser();
    $variant = ProductVariant::factory()->for(Product::factory())->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/vendors/me/offers', [
            'variant_id' => $variant->id,
            'price_amount' => 25000,
            'stock_quantity' => 5,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('status', 'pending_review');
    $response->assertJsonPath('price_amount', 25000);
});

it('rejects an offer for an unknown variant', function () {
    [$user] = activeVendorUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/vendors/me/offers', [
            'variant_id' => 999999,
            'price_amount' => 25000,
            'stock_quantity' => 5,
        ])
        ->assertUnprocessable();
});

it('rejects an offer against a variant of a draft product', function () {
    [$user] = activeVendorUser();
    $variant = ProductVariant::factory()->for(Product::factory()->draft())->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/vendors/me/offers', [
            'variant_id' => $variant->id,
            'price_amount' => 25000,
            'stock_quantity' => 5,
        ])
        ->assertUnprocessable();
});

it('lists only the vendor\'s own offers', function () {
    [$user, $vendor] = activeVendorUser();
    [, $otherVendor] = activeVendorUser();

    $variant = ProductVariant::factory()->for(Product::factory())->create();
    Offer::factory()->create([
        'product_variant_id' => $variant->id,
        'vendor_id' => $vendor->id,
    ]);
    Offer::factory()->create([
        'product_variant_id' => $variant->id,
        'vendor_id' => $otherVendor->id,
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/vendors/me/offers');

    $response->assertOk();
    expect($response->json())->toHaveCount(1);
});

it('updates the price and stock of its own offer', function () {
    [$user, $vendor] = activeVendorUser();
    $variant = ProductVariant::factory()->for(Product::factory())->create();
    $offer = Offer::factory()->create([
        'product_variant_id' => $variant->id,
        'vendor_id' => $vendor->id,
        'price_amount' => 10000,
        'stock_quantity' => 5,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/vendors/me/offers/{$offer->id}", [
            'price_amount' => 12000,
            'stock_quantity' => 3,
        ]);

    $response->assertOk();
    $response->assertJsonPath('price_amount', 12000);
    $response->assertJsonPath('stock_quantity', 3);
});

it('sends a published offer back to moderation when the price changes', function () {
    [$user, $vendor] = activeVendorUser();
    $variant = ProductVariant::factory()->for(Product::factory())->create();
    $offer = Offer::factory()->create([
        'product_variant_id' => $variant->id,
        'vendor_id' => $vendor->id,
        'status' => OfferStatus::Published,
        'price_amount' => 10000,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/vendors/me/offers/{$offer->id}", ['price_amount' => 15000]);

    $response->assertOk();
    $response->assertJsonPath('status', 'pending_review');
    expect($offer->fresh()->status)->toBe(OfferStatus::PendingReview);
});

it('keeps a published offer live when only the stock changes', function () {
    [$user, $vendor] = activeVendorUser();
    $variant = ProductVariant::factory()->for(Product::factory())->create();
    $offer = Offer::factory()->create([
        'product_variant_id' => $variant->id,
        'vendor_id' => $vendor->id,
        'status' => OfferStatus::Published,
        'stock_quantity' => 5,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/vendors/me/offers/{$offer->id}", ['stock_quantity' => 2]);

    $response->assertOk();
    $response->assertJsonPath('status', 'published');
    expect($offer->fresh()->status)->toBe(OfferStatus::Published);
});

it('refuses to update an offer belonging to another vendor', function () {
    [$user] = activeVendorUser();
    [, $otherVendor] = activeVendorUser();
    $variant = ProductVariant::factory()->for(Product::factory())->create();
    $offer = Offer::factory()->create([
        'product_variant_id' => $variant->id,
        'vendor_id' => $otherVendor->id,
    ]);

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/vendors/me/offers/{$offer->id}", ['price_amount' => 1])
        ->assertNotFound();
});

it('archives its own offer on destroy', function () {
    [$user, $vendor] = activeVendorUser();
    $variant = ProductVariant::factory()->for(Product::factory())->create();
    $offer = Offer::factory()->create([
        'product_variant_id' => $variant->id,
        'vendor_id' => $vendor->id,
    ]);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/vendors/me/offers/{$offer->id}")
        ->assertNoContent();

    expect($offer->fresh()->status)->toBe(OfferStatus::Archived);
});

it('refuses to archive an offer belonging to another vendor', function () {
    [$user] = activeVendorUser();
    [, $otherVendor] = activeVendorUser();
    $variant = ProductVariant::factory()->for(Product::factory())->create();
    $offer = Offer::factory()->create([
        'product_variant_id' => $variant->id,
        'vendor_id' => $otherVendor->id,
    ]);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/vendors/me/offers/{$offer->id}")
        ->assertNotFound();

    expect($offer->fresh()->status)->not->toBe(OfferStatus::Archived);
});
