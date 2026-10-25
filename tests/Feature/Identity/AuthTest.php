<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

it('registers a new client account and assigns the client role', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Awa Diallo',
        'email' => 'awa@example.com',
        'password' => 'Secret123',
        'password_confirmation' => 'Secret123',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('name', 'Awa Diallo');
    $response->assertJsonPath('roles.0', Role::Client->value);

    $user = User::query()->where('email', 'awa@example.com')->first();
    expect($user)->not->toBeNull();
    expect(Hash::check('Secret123', (string) $user->password))->toBeTrue();
});

it('rejects registration when passwords do not match', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Awa Diallo',
        'email' => 'awa@example.com',
        'password' => 'Secret123',
        'password_confirmation' => 'Mismatch123',
    ])->assertUnprocessable();
});

it('rejects registration with a duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Someone',
        'email' => 'taken@example.com',
        'password' => 'Secret123',
        'password_confirmation' => 'Secret123',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('logs a user in and returns a bearer token', function () {
    User::factory()->create([
        'email' => 'awa@example.com',
        'password' => Hash::make('Secret123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'awa@example.com',
        'password' => 'Secret123',
        'device_name' => 'test-suite',
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['user', 'token']);
});

it('refuses login with a wrong password', function () {
    User::factory()->create(['email' => 'awa@example.com', 'password' => Hash::make('Secret123')]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'awa@example.com',
        'password' => 'wrong-password',
        'device_name' => 'test-suite',
    ])->assertStatus(401);
});

it('refuses login for an inactive account', function () {
    User::factory()->inactive()->create([
        'email' => 'awa@example.com',
        'password' => Hash::make('Secret123'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'awa@example.com',
        'password' => 'Secret123',
        'device_name' => 'test-suite',
    ])->assertStatus(403);
});

it('returns the authenticated user on /auth/me', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('id', $user->id);
});

it('rejects /auth/me without a token', function () {
    $this->getJson('/api/v1/auth/me')->assertStatus(401);
});

it('revokes the current token on logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-suite')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout');

    $response->assertStatus(204);
    expect($user->tokens()->count())->toBe(0);
});
