<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Category;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function catalogAdminUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::GestionnaireCatalogue->value);

    return $user;
}

it('rejects an anonymous request to list admin categories', function () {
    $this->getJson('/api/v1/admin/categories')->assertUnauthorized();
});

it('rejects roles that are backoffice-adjacent but not catalog management', function (Role $role) {
    $user = User::factory()->create();
    $user->assignRole($role->value);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/admin/categories')
        ->assertForbidden();
})->with([
    Role::VendeurTiers,
    Role::Marketing,
    Role::OperateurCommandes,
    Role::GestionnaireStock,
]);

it('lists categories for an authenticated catalog admin', function () {
    Category::factory()->count(2)->create();

    $response = $this->actingAs(catalogAdminUser(), 'sanctum')
        ->getJson('/api/v1/admin/categories');

    $response->assertOk();
    expect($response->json())->toHaveCount(2);
});

it('creates a category with an auto-generated slug', function () {
    $response = $this->actingAs(catalogAdminUser(), 'sanctum')
        ->postJson('/api/v1/admin/categories', ['name' => 'Téléphonie Mobile']);

    $response->assertCreated();
    $response->assertJsonPath('slug', 'telephonie-mobile');
});

it('rejects a category with a duplicate slug', function () {
    Category::factory()->create(['slug' => 'taken']);

    $this->actingAs(catalogAdminUser(), 'sanctum')
        ->postJson('/api/v1/admin/categories', ['name' => 'Autre', 'slug' => 'taken'])
        ->assertUnprocessable();
});

it('updates a category', function () {
    $category = Category::factory()->create(['name' => 'Old Name']);

    $response = $this->actingAs(catalogAdminUser(), 'sanctum')
        ->putJson("/api/v1/admin/categories/{$category->id}", ['name' => 'New Name']);

    $response->assertOk();
    $response->assertJsonPath('name', 'New Name');
});

it('refuses to make a category its own parent', function () {
    $category = Category::factory()->create();

    $this->actingAs(catalogAdminUser(), 'sanctum')
        ->putJson("/api/v1/admin/categories/{$category->id}", ['parent_id' => $category->id])
        ->assertUnprocessable();
});

it('deletes an empty category', function () {
    $category = Category::factory()->create();

    $this->actingAs(catalogAdminUser(), 'sanctum')
        ->deleteJson("/api/v1/admin/categories/{$category->id}")
        ->assertNoContent();

    expect(Category::query()->find($category->id))->toBeNull();
});

it('refuses to delete a category that still has products attached', function () {
    $category = Category::factory()->create();
    Product::factory()->create(['category_id' => $category->id]);

    $this->actingAs(catalogAdminUser(), 'sanctum')
        ->deleteJson("/api/v1/admin/categories/{$category->id}")
        ->assertStatus(409);

    expect(Category::query()->find($category->id))->not->toBeNull();
});
