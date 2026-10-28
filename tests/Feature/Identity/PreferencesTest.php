<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Notifications\Infrastructure\Models\Notification;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function prefsUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Client->value);

    return $user;
}

it('requires authentication', function () {
    $this->putJson('/api/v1/auth/preferences', ['locale' => 'en'])->assertUnauthorized();
});

it('saves the language and the accent colour, and returns them with the account', function () {
    $user = prefsUser();

    $this->actingAs($user, 'sanctum')->putJson('/api/v1/auth/preferences', ['locale' => 'en', 'accent_color' => '#2563EB'])
        ->assertOk()
        ->assertJsonPath('locale', 'en')
        ->assertJsonPath('accent_color', '#2563EB');

    $this->actingAs($user->fresh(), 'sanctum')->getJson('/api/v1/auth/me')
        ->assertJsonPath('locale', 'en')->assertJsonPath('accent_color', '#2563EB');
});

it('changes only the fields that were sent', function () {
    $user = prefsUser();
    $user->update(['locale' => 'en', 'accent_color' => '#2563eb']);

    $this->actingAs($user, 'sanctum')->putJson('/api/v1/auth/preferences', ['accent_color' => '#e11d48'])->assertOk();
    expect($user->fresh()->locale)->toBe('en')->and($user->fresh()->accent_color)->toBe('#e11d48');

    $this->actingAs($user, 'sanctum')->putJson('/api/v1/auth/preferences', ['locale' => 'fr'])->assertOk();
    expect($user->fresh()->locale)->toBe('fr')->and($user->fresh()->accent_color)->toBe('#e11d48');
});

it('goes back to the default colour when null is sent', function () {
    $user = prefsUser();
    $user->update(['accent_color' => '#2563eb']);

    $this->actingAs($user, 'sanctum')->putJson('/api/v1/auth/preferences', ['accent_color' => null])->assertOk();

    expect($user->fresh()->accent_color)->toBeNull();
});

it('refuses anything that is not a supported language or a six-digit hex colour', function () {
    $user = prefsUser();

    foreach ([
        ['locale' => 'de'],
        ['locale' => ''],
        ['accent_color' => 'red'],
        ['accent_color' => '#fff'],
        ['accent_color' => 'url(javascript:alert(1))'],
        ['accent_color' => '#12345g'],
    ] as $payload) {
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/auth/preferences', $payload)->assertUnprocessable();
    }

    expect($user->fresh()->locale)->toBe('fr')->and($user->fresh()->accent_color)->toBeNull();
});

it('cannot change anything else through the preferences endpoint', function () {
    $user = prefsUser();

    $this->actingAs($user, 'sanctum')->putJson('/api/v1/auth/preferences', ['locale' => 'en', 'name' => 'Hacker', 'email' => 'h@x.io', 'roles' => ['administrateur']])->assertOk();

    expect($user->fresh()->name)->not->toBe('Hacker')->and($user->fresh()->hasRole(Role::Administrateur->value))->toBeFalse();
});

it('remembers the language a new account was created in', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Awa Diallo', 'email' => 'awa@example.com', 'password' => 'Secret123', 'password_confirmation' => 'Secret123', 'locale' => 'en',
    ])->assertCreated()->assertJsonPath('locale', 'en');

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Moussa', 'email' => 'moussa@example.com', 'password' => 'Secret123', 'password_confirmation' => 'Secret123',
    ])->assertCreated()->assertJsonPath('locale', 'fr');
});

it('uses the signed-in customer\'s language for their order messages when none is sent', function () {
    $user = prefsUser();
    $user->update(['locale' => 'en']);
    $offer = Offer::factory()->create(['price_amount' => 10_000, 'stock_quantity' => 3]);

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/orders', [
        'items' => [['offer_id' => $offer->id, 'quantity' => 1]],
        'customer_name' => 'Awa', 'customer_phone' => '0700000031', 'delivery_address' => 'Cocody',
    ])->assertCreated();

    expect(Notification::query()->where('event', 'order.placed')->sole()->body)->toContain('cash on delivery');
});

it('saves the theme, returns it, and goes back to automatic when null is sent', function () {
    $user = prefsUser();

    $this->actingAs($user, 'sanctum')->putJson('/api/v1/auth/preferences', ['theme' => 'dark'])->assertOk()->assertJsonPath('theme', 'dark');
    expect($user->fresh()->theme)->toBe('dark');

    $this->actingAs($user, 'sanctum')->putJson('/api/v1/auth/preferences', ['theme' => null])->assertOk()->assertJsonPath('theme', null);
    expect($user->fresh()->theme)->toBeNull();
});

it('refuses an unknown theme', function () {
    $user = prefsUser();

    foreach (['neon', 'DARK', 7] as $theme) {
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/auth/preferences', ['theme' => $theme])->assertUnprocessable();
    }
});
