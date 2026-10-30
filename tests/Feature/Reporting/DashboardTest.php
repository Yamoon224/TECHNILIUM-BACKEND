<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Reporting\Application\Dashboard\DashboardService;
use App\Modules\Shared\Dashboard\DashboardSection;
use App\Modules\Shared\Dashboard\Widgets;
use Carbon\CarbonInterface;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function staffWith(Role $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

/** @return list<string> */
function dashboardKeys(User $user, string $query = ''): array
{
    return array_column(test()->actingAs($user, 'sanctum')->getJson('/api/v1/admin/dashboard'.$query)->assertOk()->json('sections'), 'key');
}

it('is for signed-in staff only', function () {
    $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
    $this->actingAs(staffWith(Role::Client), 'sanctum')->getJson('/api/v1/admin/dashboard')->assertForbidden();
});

it('gives the direction the management blocks first', function () {
    $keys = dashboardKeys(staffWith(Role::Administrateur));

    expect(array_slice($keys, 0, 3))->toBe(['sales', 'finance', 'alerts']);
});

it('gives each profile the blocks of its own features only', function () {
    $support = dashboardKeys(staffWith(Role::Support));

    expect($support)->not->toContain('sales')
        ->and($support)->not->toContain('finance')
        ->and($support)->not->toContain('access');

    $accountant = dashboardKeys(staffWith(Role::Comptable));
    expect($accountant)->toContain('finance')->and($accountant[0])->toBe('finance');
});

it('describes the profile and the period', function () {
    $response = $this->actingAs(staffWith(Role::Comptable), 'sanctum')->getJson('/api/v1/admin/dashboard?period=7d')->assertOk();

    expect($response->json('period.key'))->toBe('7d')
        ->and($response->json('roles.0.slug'))->toBe('comptable')
        ->and($response->json('roles.0.label'))->toBe(Role::Comptable->label());

    // An unknown period falls back to 30 days.
    expect($this->actingAs(staffWith(Role::Comptable), 'sanctum')->getJson('/api/v1/admin/dashboard?period=forever')->json('period.key'))->toBe('30d');
});

it('renders every block in the generic shape', function () {
    $sections = $this->actingAs(staffWith(Role::SuperAdmin), 'sanctum')->getJson('/api/v1/admin/dashboard')->assertOk()->json('sections');

    expect($sections)->not->toBeEmpty();
    foreach ($sections as $section) {
        expect($section)->toHaveKeys(['key', 'ability', 'title', 'title_en', 'link', 'kpis', 'charts', 'tables'])
            ->and($section['error'] ?? false)->toBeFalse();
        foreach ($section['kpis'] as $kpi) {
            expect($kpi)->toHaveKeys(['key', 'label', 'label_en', 'value', 'format', 'tone']);
        }
    }
});

it('keeps one failing block from breaking the home page', function () {
    $broken = new class implements DashboardSection
    {
        public function key(): string
        {
            return 'broken';
        }

        public function ability(): string
        {
            return 'view-reports';
        }

        public function build(CarbonInterface $from, CarbonInterface $to): array
        {
            throw new RuntimeException('boom');
        }
    };
    $fine = new class implements DashboardSection
    {
        public function key(): string
        {
            return 'fine';
        }

        public function ability(): string
        {
            return 'view-reports';
        }

        public function build(CarbonInterface $from, CarbonInterface $to): array
        {
            return Widgets::section('Fine', 'Fine', '/');
        }
    };

    $blocks = (new DashboardService([$broken, $fine]))->sectionsFor(['administrateur'], fn () => true, now()->subDay(), now());

    expect(array_column($blocks, 'key'))->toBe(['broken', 'fine'])
        ->and($blocks[0]['error'])->toBeTrue()
        ->and($blocks[1]['title'])->toBe('Fine');
});
