<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\OfferStatus;
use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function catalogModerator(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::GestionnaireCatalogue->value);

    return $user;
}

it('rejects an anonymous request to list offers for moderation', function () {
    $this->getJson('/api/v1/admin/offers')->assertUnauthorized();
});

it('rejects a role without catalog management from moderating offers', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::OperateurCommandes->value);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/admin/offers')
        ->assertForbidden();
});

it('lists pending-review offers by default', function () {
    Offer::factory()->pendingReview()->create();
    Offer::factory()->create(); // published by default

    $response = $this->actingAs(catalogModerator(), 'sanctum')->getJson('/api/v1/admin/offers');

    $response->assertOk();
    expect($response->json())->toHaveCount(1);
    $response->assertJsonPath('0.status', 'pending_review');
});

it('approves a pending offer', function () {
    $offer = Offer::factory()->pendingReview()->create();

    $response = $this->actingAs(catalogModerator(), 'sanctum')
        ->postJson("/api/v1/admin/offers/{$offer->id}/approve");

    $response->assertOk();
    $response->assertJsonPath('status', 'published');
    expect($offer->fresh()->status)->toBe(OfferStatus::Published);
});

it('rejects a pending offer', function () {
    $offer = Offer::factory()->pendingReview()->create();

    $response = $this->actingAs(catalogModerator(), 'sanctum')
        ->postJson("/api/v1/admin/offers/{$offer->id}/reject");

    $response->assertOk();
    $response->assertJsonPath('status', 'rejected');
});

it('refuses to approve an already-published offer', function () {
    $offer = Offer::factory()->create(); // published

    $this->actingAs(catalogModerator(), 'sanctum')
        ->postJson("/api/v1/admin/offers/{$offer->id}/approve")
        ->assertStatus(422);
});
