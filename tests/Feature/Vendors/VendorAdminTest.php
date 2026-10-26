<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Vendors\Domain\VendorStatus;
use App\Modules\Vendors\Infrastructure\Models\Vendor;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function administrateur(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Administrateur->value);

    return $user;
}

it('rejects an anonymous request to list vendors', function () {
    $this->getJson('/api/v1/admin/vendors')->assertUnauthorized();
});

it('rejects roles other than administrateur from managing vendors', function (Role $role) {
    $user = User::factory()->create();
    $user->assignRole($role->value);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/admin/vendors')
        ->assertForbidden();
})->with([
    Role::GestionnaireCatalogue,
    Role::OperateurCommandes,
    Role::VendeurTiers,
    Role::Client,
]);

it('lists vendors for an administrateur', function () {
    Vendor::factory()->count(2)->create();

    $response = $this->actingAs(administrateur(), 'sanctum')->getJson('/api/v1/admin/vendors');

    $response->assertOk();
    expect($response->json())->toHaveCount(2);
});

it('filters vendors by status', function () {
    Vendor::factory()->create();
    Vendor::factory()->active()->create();

    $response = $this->actingAs(administrateur(), 'sanctum')
        ->getJson('/api/v1/admin/vendors?status=active');

    $response->assertOk();
    expect($response->json())->toHaveCount(1);
    $response->assertJsonPath('0.status', 'active');
});

it('approves a pending vendor', function () {
    $vendor = Vendor::factory()->create();

    $response = $this->actingAs(administrateur(), 'sanctum')
        ->postJson("/api/v1/admin/vendors/{$vendor->id}/approve");

    $response->assertOk();
    $response->assertJsonPath('status', 'active');
    expect($vendor->fresh()->status)->toBe(VendorStatus::Active);
});

it('suspends an active vendor', function () {
    $vendor = Vendor::factory()->active()->create();

    $response = $this->actingAs(administrateur(), 'sanctum')
        ->postJson("/api/v1/admin/vendors/{$vendor->id}/suspend");

    $response->assertOk();
    $response->assertJsonPath('status', 'suspended');
});

it('reactivates a suspended vendor', function () {
    $vendor = Vendor::factory()->suspended()->create();

    $response = $this->actingAs(administrateur(), 'sanctum')
        ->postJson("/api/v1/admin/vendors/{$vendor->id}/reactivate");

    $response->assertOk();
    $response->assertJsonPath('status', 'active');
});

it('refuses to re-approve an already active vendor', function () {
    $vendor = Vendor::factory()->active()->create();

    $this->actingAs(administrateur(), 'sanctum')
        ->postJson("/api/v1/admin/vendors/{$vendor->id}/approve")
        ->assertStatus(422);
});
