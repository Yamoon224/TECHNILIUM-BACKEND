<?php

declare(strict_types=1);

use App\Modules\Audit\Infrastructure\Models\AuditLog;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function accessUser(Role|string $role, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole($role instanceof Role ? $role->value : $role);

    return $user;
}

function asSuperAdmin(): User
{
    $super = accessUser(Role::SuperAdmin);
    Sanctum::actingAs($super);

    return $super;
}

it('keeps access management to the super admin', function () {
    $this->getJson('/api/v1/admin/access')->assertUnauthorized();

    foreach ([Role::Administrateur, Role::Comptable, Role::Client] as $role) {
        Sanctum::actingAs(accessUser($role));
        $this->getJson('/api/v1/admin/access')->assertForbidden();
        $this->putJson('/api/v1/admin/access/switches', ['feature' => 'manage-orders', 'subject_type' => 'role', 'subject' => 'comptable', 'enabled' => false])->assertForbidden();
    }

    asSuperAdmin();
    $this->getJson('/api/v1/admin/access')->assertOk()->assertJsonStructure(['features', 'groups', 'switches']);
});

it('lets the super admin use every feature', function () {
    asSuperAdmin();

    $this->getJson('/api/v1/admin/audit')->assertOk();
    $this->getJson('/api/v1/admin/payments')->assertOk();
});

it('hides a feature from a whole group, in the API and in the account', function () {
    $accountant = accessUser(Role::Comptable);
    Sanctum::actingAs($accountant);
    $this->getJson('/api/v1/admin/invoices')->assertOk();
    expect($this->getJson('/api/v1/auth/me')->json('abilities'))->toContain('manage-invoices');

    asSuperAdmin();
    $this->putJson('/api/v1/admin/access/switches', ['feature' => 'manage-invoices', 'subject_type' => 'role', 'subject' => 'comptable', 'enabled' => false])->assertNoContent();

    Sanctum::actingAs($accountant->fresh());
    $this->getJson('/api/v1/admin/invoices')->assertForbidden();
    $this->getJson('/api/v1/auth/me')->assertJsonPath('disabled_features', ['manage-invoices'])
        ->assertJsonMissing(['abilities' => ['manage-invoices']]);
    expect($this->getJson('/api/v1/auth/me')->json('abilities'))->not->toContain('manage-invoices')->toContain('manage-payments');
});

it('can switch a feature off for the administrator too', function () {
    $admin = accessUser(Role::Administrateur);
    asSuperAdmin();
    $this->putJson('/api/v1/admin/access/switches', ['feature' => 'view-audit', 'subject_type' => 'user', 'subject' => (string) $admin->id, 'enabled' => false])->assertNoContent();

    Sanctum::actingAs($admin->fresh());
    $this->getJson('/api/v1/admin/audit')->assertForbidden();
    $this->getJson('/api/v1/admin/payments')->assertOk();
});

it('lets a switch on one user win over the switch on their group', function () {
    $one = accessUser(Role::Comptable);
    $other = accessUser(Role::Comptable);
    asSuperAdmin();
    $this->putJson('/api/v1/admin/access/switches', ['feature' => 'manage-invoices', 'subject_type' => 'role', 'subject' => 'comptable', 'enabled' => false])->assertNoContent();
    $this->putJson('/api/v1/admin/access/switches', ['feature' => 'manage-invoices', 'subject_type' => 'user', 'subject' => (string) $one->id, 'enabled' => true])->assertNoContent();

    Sanctum::actingAs($one->fresh());
    $this->getJson('/api/v1/admin/invoices')->assertOk();
    Sanctum::actingAs($other->fresh());
    $this->getJson('/api/v1/admin/invoices')->assertForbidden();
});

it('goes back to the default when the switch is cleared', function () {
    $accountant = accessUser(Role::Comptable);
    asSuperAdmin();
    $payload = ['feature' => 'manage-invoices', 'subject_type' => 'user', 'subject' => (string) $accountant->id];
    $this->putJson('/api/v1/admin/access/switches', $payload + ['enabled' => false])->assertNoContent();
    $this->putJson('/api/v1/admin/access/switches', $payload + ['enabled' => null])->assertNoContent();

    Sanctum::actingAs($accountant->fresh());
    $this->getJson('/api/v1/admin/invoices')->assertOk();
    $this->assertDatabaseCount('feature_overrides', 0);
});

it('refuses to restrict the super admin, and unknown features or targets', function () {
    $super = asSuperAdmin();

    $this->putJson('/api/v1/admin/access/switches', ['feature' => 'manage-orders', 'subject_type' => 'user', 'subject' => (string) $super->id, 'enabled' => false])->assertUnprocessable();
    $this->putJson('/api/v1/admin/access/switches', ['feature' => 'manage-orders', 'subject_type' => 'role', 'subject' => 'super_admin', 'enabled' => false])->assertUnprocessable();
    $this->putJson('/api/v1/admin/access/switches', ['feature' => 'inconnue', 'subject_type' => 'role', 'subject' => 'comptable', 'enabled' => false])->assertUnprocessable();
    $this->putJson('/api/v1/admin/access/switches', ['feature' => 'manage-orders', 'subject_type' => 'role', 'subject' => 'fantome', 'enabled' => false])->assertUnprocessable();
});

it('creates a custom group with only the features ticked', function () {
    asSuperAdmin();

    $created = $this->postJson('/api/v1/admin/access/groups', [
        'label' => 'Caisse boutique', 'description' => 'Encaissement', 'permissions' => ['manage-payments', 'manage-orders'],
    ])->assertCreated()->assertJsonPath('builtin', false)->json();

    expect($created['name'])->toBe('groupe_caisse_boutique')->and($created['permissions'])->toBe(['manage-orders', 'manage-payments']);

    $member = accessUser($created['name']);
    Sanctum::actingAs($member);
    $this->getJson('/api/v1/admin/payments')->assertOk();
    $this->getJson('/api/v1/admin/stock/movements')->assertForbidden();
    $this->getJson('/api/v1/admin/audit')->assertForbidden();
    expect($this->getJson('/api/v1/auth/me')->json('abilities'))->toBe(['manage-orders', 'manage-payments']);
});

it('changes the features of a custom group and the effect is immediate', function () {
    asSuperAdmin();
    $name = $this->postJson('/api/v1/admin/access/groups', ['label' => 'Équipe', 'permissions' => ['manage-payments']])->json('name');
    $member = accessUser($name);

    Sanctum::actingAs($member);
    $this->getJson('/api/v1/admin/payments')->assertOk();

    asSuperAdmin();
    $this->putJson("/api/v1/admin/access/groups/{$name}", ['label' => 'Équipe finance', 'permissions' => ['view-reports']])->assertOk()->assertJsonPath('label', 'Équipe finance');

    Sanctum::actingAs($member->fresh());
    $this->getJson('/api/v1/admin/payments')->assertForbidden();
});

it('gives each group of the same name a distinct slug', function () {
    asSuperAdmin();
    $first = $this->postJson('/api/v1/admin/access/groups', ['label' => 'Caisse', 'permissions' => []])->json('name');
    $second = $this->postJson('/api/v1/admin/access/groups', ['label' => 'Caisse', 'permissions' => []])->json('name');

    expect($second)->not->toBe($first);
});

it('never edits or deletes a built-in profile', function () {
    asSuperAdmin();

    $this->putJson('/api/v1/admin/access/groups/comptable', ['label' => 'X', 'permissions' => []])->assertUnprocessable();
    $this->deleteJson('/api/v1/admin/access/groups/administrateur')->assertUnprocessable();
});

it('refuses to delete a group that still has members, then deletes it once empty', function () {
    asSuperAdmin();
    $name = $this->postJson('/api/v1/admin/access/groups', ['label' => 'Temporaire', 'permissions' => ['manage-orders']])->json('name');
    $member = accessUser($name);

    $this->deleteJson("/api/v1/admin/access/groups/{$name}")->assertUnprocessable();

    $this->putJson("/api/v1/admin/access/users/{$member->id}/roles", ['roles' => ['support']])->assertOk()->assertJsonPath('roles', ['support']);
    $this->deleteJson("/api/v1/admin/access/groups/{$name}")->assertNoContent();
    expect(RoleModel::query()->where('name', $name)->exists())->toBeFalse();
});

it('validates the permissions of a group', function () {
    asSuperAdmin();

    $this->postJson('/api/v1/admin/access/groups', ['label' => 'X'])->assertUnprocessable();
    $this->postJson('/api/v1/admin/access/groups', ['label' => 'Groupe', 'permissions' => ['tout-faire']])->assertUnprocessable();
    $this->postJson('/api/v1/admin/access/groups', ['label' => '', 'permissions' => []])->assertUnprocessable();
});

it('assigns groups to an account, but never lets the super admin drop their own role', function () {
    $super = asSuperAdmin();
    $user = accessUser(Role::Support);

    $this->putJson("/api/v1/admin/access/users/{$user->id}/roles", ['roles' => ['support', 'marketing']])->assertOk();
    expect($user->fresh()->getRoleNames()->sort()->values()->all())->toBe(['marketing', 'support']);

    $this->putJson("/api/v1/admin/access/users/{$user->id}/roles", ['roles' => ['groupe_fantome']])->assertUnprocessable();
    $this->putJson("/api/v1/admin/access/users/{$super->id}/roles", ['roles' => ['administrateur']])->assertUnprocessable();
    expect($super->fresh()->hasRole('super_admin'))->toBeTrue();
});

it('lists the team accounts with their switches, without customers', function () {
    asSuperAdmin();
    $staff = accessUser(Role::Support, ['name' => 'Aminata Équipe']);
    accessUser(Role::Client, ['name' => 'Cliente Simple']);
    $this->putJson('/api/v1/admin/access/switches', ['feature' => 'manage-support', 'subject_type' => 'user', 'subject' => (string) $staff->id, 'enabled' => false])->assertNoContent();

    $rows = collect($this->getJson('/api/v1/admin/access/users')->assertOk()->json());

    expect($rows->pluck('name'))->toContain('Aminata Équipe')->not->toContain('Cliente Simple')
        ->and($rows->firstWhere('id', $staff->id)['switches'])->toBe(['manage-support' => false]);
    expect(collect($this->getJson('/api/v1/admin/access/users?search=Aminata')->json())->count())->toBe(1);
});

it('records every access change in the audit trail, for custom groups too', function () {
    asSuperAdmin();
    $name = $this->postJson('/api/v1/admin/access/groups', ['label' => 'Audit me', 'permissions' => ['manage-orders']])->json('name');
    $this->putJson('/api/v1/admin/access/switches', ['feature' => 'manage-orders', 'subject_type' => 'role', 'subject' => $name, 'enabled' => false])->assertNoContent();

    $member = accessUser($name);
    Sanctum::actingAs($member);
    $this->postJson('/api/v1/admin/access/groups', ['label' => 'Tentative', 'permissions' => []])->assertForbidden();

    $paths = AuditLog::query()->pluck('path')->all();
    expect($paths)->toContain('/api/v1/admin/access/groups')->toContain('/api/v1/admin/access/switches');
    expect(AuditLog::query()->where('user_id', $member->id)->where('status', 403)->exists())->toBeTrue();
});

it('exposes no abilities to a customer', function () {
    Sanctum::actingAs(accessUser(Role::Client));

    $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('abilities', [])->assertJsonPath('disabled_features', []);
});
