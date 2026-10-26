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

it('creates a pending vendor account and profile', function () {
    $response = $this->postJson('/api/v1/vendors/apply', [
        'name' => 'Awa Diallo',
        'email' => 'awa@example.com',
        'phone' => '0700000000',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'company_name' => 'Awa Import',
        'address' => 'Abidjan, Cocody',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('status', 'pending');
    $response->assertJsonPath('company_name', 'Awa Import');

    $user = User::where('email', 'awa@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole(Role::VendeurTiers->value))->toBeTrue();

    $vendor = Vendor::where('user_id', $user->id)->first();
    expect($vendor)->not->toBeNull();
    expect($vendor->status)->toBe(VendorStatus::Pending);
});

it('rejects a vendor application with a duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/v1/vendors/apply', [
        'name' => 'Awa Diallo',
        'email' => 'taken@example.com',
        'phone' => '0700000000',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'company_name' => 'Awa Import',
    ])->assertUnprocessable();
});

it('rejects a vendor application with mismatched password confirmation', function () {
    $this->postJson('/api/v1/vendors/apply', [
        'name' => 'Awa Diallo',
        'email' => 'awa2@example.com',
        'phone' => '0700000001',
        'password' => 'password123',
        'password_confirmation' => 'nope',
        'company_name' => 'Awa Import',
    ])->assertUnprocessable();
});
