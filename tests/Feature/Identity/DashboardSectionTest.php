<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Dashboard\AccessSection;
use App\Modules\Identity\Domain\AccessCatalogue;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\FeatureOverride;
use App\Modules\Identity\Infrastructure\Models\User;
use Carbon\CarbonImmutable;
use Spatie\Permission\Models\Role as RoleModel;

function accessKpi(array $section, string $key): array
{
    return collect($section['kpis'])->firstWhere('key', $key);
}

function dashboardAccessUser(string $role, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

it('declares the access ability and a key', function () {
    $section = app(AccessSection::class);

    expect($section->key())->toBe('access')
        ->and($section->ability())->toBe(AccessCatalogue::MANAGE_ACCESS);
});

it('summarises accounts, groups and roles', function () {
    $now = CarbonImmutable::now();

    dashboardAccessUser(Role::Comptable->value, ['name' => 'Fatou']);
    dashboardAccessUser(Role::Comptable->value);
    dashboardAccessUser(Role::Support->value, ['is_active' => false]);
    dashboardAccessUser(Role::Client->value);
    dashboardAccessUser(Role::Client->value);
    dashboardAccessUser(Role::Client->value);
    $group = RoleModel::findOrCreate('equipe_nuit', 'web');
    $group->forceFill(['label' => 'Équipe de nuit'])->save();
    dashboardAccessUser('equipe_nuit');
    FeatureOverride::query()->create(['feature' => 'manage-orders', 'subject_type' => FeatureOverride::ROLE, 'subject' => 'support', 'enabled' => false]);

    $section = app(AccessSection::class)->build($now->subDays(6)->startOfDay(), $now->endOfDay());

    expect($section['link'])->toBe('/acces')
        ->and(accessKpi($section, 'staff')['value'])->toBe(4)
        ->and(accessKpi($section, 'customers')['value'])->toBe(3)
        ->and(accessKpi($section, 'disabled_accounts')['value'])->toBe(1)
        ->and(accessKpi($section, 'switched_off')['value'])->toBe(1)
        ->and(accessKpi($section, 'custom_groups')['value'])->toBe(1);

    $points = collect($section['charts'][0]['points'])->pluck('value', 'label');
    expect($points['Comptable / Finance'])->toBe(2)
        ->and($points['Support / SAV'])->toBe(1)
        ->and($points['Équipe de nuit'])->toBe(1)
        ->and($points)->not->toHaveKey('Client');

    $rows = $section['tables'][0]['rows'];
    expect($rows)->toHaveCount(4)
        ->and($rows[0][1])->toBe('Équipe de nuit')
        ->and(collect($rows)->firstWhere(0, 'Fatou')[1])->toBe('Comptable / Finance');
});

it('copes with an empty database', function () {
    $now = CarbonImmutable::now();
    $section = app(AccessSection::class)->build($now->subDays(6)->startOfDay(), $now->endOfDay());

    expect(accessKpi($section, 'staff')['value'])->toBe(0)
        ->and(accessKpi($section, 'customers')['value'])->toBe(0)
        ->and(accessKpi($section, 'custom_groups')['value'])->toBe(0)
        ->and($section['charts'][0]['points'])->toBe([])
        ->and($section['tables'][0]['rows'])->toBe([]);
});
