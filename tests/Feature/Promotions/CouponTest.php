<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Promotions\Infrastructure\Models\Coupon;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function marketer(Role $role = Role::Marketing): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

it('restricts coupon management to marketing and administrators', function () {
    $this->getJson('/api/v1/admin/coupons')->assertUnauthorized();

    foreach ([Role::Support, Role::Comptable, Role::VendeurTiers, Role::Client] as $role) {
        $this->actingAs(marketer($role), 'sanctum')->getJson('/api/v1/admin/coupons')->assertForbidden();
    }

    $this->actingAs(marketer(Role::Administrateur), 'sanctum')->getJson('/api/v1/admin/coupons')->assertOk();
});

it('creates a coupon with its code normalised to upper case', function () {
    $this->actingAs(marketer(), 'sanctum')
        ->postJson('/api/v1/admin/coupons', ['code' => ' bienvenue10 ', 'type' => 'percent', 'value' => 10, 'max_uses' => 100])
        ->assertCreated()
        ->assertJsonPath('code', 'BIENVENUE10')
        ->assertJsonPath('used_count', 0)
        ->assertJsonPath('active', true);
});

it('rejects a duplicate code whatever its case, an invalid percentage and a bad window', function (array $payload) {
    Coupon::factory()->create(['code' => 'DOUBLON']);

    $this->actingAs(marketer(), 'sanctum')
        ->postJson('/api/v1/admin/coupons', $payload + ['type' => 'percent', 'value' => 10])
        ->assertUnprocessable();
})->with([
    'duplicate' => [['code' => 'doublon']],
    'percent over 100' => [['code' => 'GROS', 'value' => 150]],
    'bad characters' => [['code' => 'a b!']],
    'ends before start' => [['code' => 'FENETRE', 'starts_at' => '2026-12-01', 'ends_at' => '2026-11-01']],
]);

it('allows a fixed amount above 100', function () {
    $this->actingAs(marketer(), 'sanctum')
        ->postJson('/api/v1/admin/coupons', ['code' => 'MINUS5K', 'type' => 'fixed', 'value' => 5_000])
        ->assertCreated();
});

it('deactivates and reactivates a coupon instead of deleting it', function () {
    $coupon = Coupon::factory()->create();
    $user = marketer();

    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/coupons/{$coupon->id}/deactivate")
        ->assertOk()->assertJsonPath('active', false);
    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/coupons/{$coupon->id}/activate")
        ->assertOk()->assertJsonPath('active', true);
});

it('quotes the discount of a valid code, case-insensitively', function () {
    Coupon::factory()->create(['code' => 'SOLDES20', 'value' => 20]);

    $this->postJson('/api/v1/coupons/quote', ['code' => 'soldes20', 'subtotal' => 50_000])
        ->assertOk()
        ->assertJsonPath('code', 'SOLDES20')
        ->assertJsonPath('discount_amount', 10_000)
        ->assertJsonPath('total_after_discount', 40_000);
});

it('refuses unknown and deactivated codes with the same message', function () {
    Coupon::factory()->create(['code' => 'EteINT', 'active' => false]);

    $unknown = $this->postJson('/api/v1/coupons/quote', ['code' => 'NOPE', 'subtotal' => 10_000])->assertUnprocessable();
    $disabled = $this->postJson('/api/v1/coupons/quote', ['code' => 'ETEINT', 'subtotal' => 10_000])->assertUnprocessable();

    expect($unknown->json('message'))->toBe($disabled->json('message'));
});

it('refuses a code outside its window, below its minimum or past its limits', function (array $state, string $needle) {
    Coupon::factory()->create(['code' => 'REGLES'] + $state);

    $this->postJson('/api/v1/coupons/quote', ['code' => 'REGLES', 'subtotal' => 10_000])
        ->assertUnprocessable()
        ->assertJsonPath('message', fn (string $m) => str_contains($m, $needle));
})->with([
    'expired' => [['ends_at' => '2020-01-01 00:00:00'], 'expiré'],
    'not started' => [['starts_at' => '2999-01-01 00:00:00'], 'pas encore'],
    'below minimum' => [['min_order_amount' => 50_000], '50000'],
    'exhausted' => [['max_uses' => 5, 'used_count' => 5], 'maximal'],
]);

it('applies a fixed coupon but never beyond the subtotal', function () {
    Coupon::factory()->fixed(100_000)->create(['code' => 'GROSBON']);

    $this->postJson('/api/v1/coupons/quote', ['code' => 'GROSBON', 'subtotal' => 8_000])
        ->assertOk()
        ->assertJsonPath('discount_amount', 8_000)
        ->assertJsonPath('total_after_discount', 0);
});

it('validates the quote input', function () {
    $this->postJson('/api/v1/coupons/quote', ['code' => '', 'subtotal' => 0])->assertUnprocessable();
});
