<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Dashboard;

use App\Modules\Identity\Domain\AccessCatalogue;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\FeatureOverride;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Shared\Dashboard\DashboardSection;
use App\Modules\Shared\Dashboard\Widgets;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * Dashboard block of the access feature: staff and customer accounts, switched-off
 * accounts and features, custom groups, and staff accounts per role.
 */
final class AccessSection implements DashboardSection
{
    public function key(): string
    {
        return 'access';
    }

    public function ability(): string
    {
        return AccessCatalogue::MANAGE_ACCESS;
    }

    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        $staff = $this->staff()->count();
        $customers = User::query()->role(Role::Client->value)->count();
        $disabledAccounts = User::query()->where('is_active', false)->count();
        $switchedOff = FeatureOverride::query()->where('enabled', false)->count();

        $builtin = array_map(fn (Role $role) => $role->value, Role::cases());
        $customGroups = RoleModel::query()->where('guard_name', 'web')->whereNotIn('name', $builtin)->count();

        $rolesTable = (string) config('permission.table_names.roles');
        $pivot = (string) config('permission.table_names.model_has_roles');

        $byRole = DB::table($rolesTable)
            ->join($pivot, $pivot.'.role_id', '=', $rolesTable.'.id')
            ->where($rolesTable.'.name', '!=', Role::Client->value)
            ->groupBy($rolesTable.'.id', $rolesTable.'.name', $rolesTable.'.label')
            ->orderBy($rolesTable.'.id')
            ->get([$rolesTable.'.name', $rolesTable.'.label', DB::raw('count(distinct '.$pivot.'.model_id) as total')])
            ->map(fn ($row) => [
                'label' => Role::tryFrom($row->name)?->label() ?? ($row->label ?: $row->name),
                'value' => (int) $row->total,
            ])
            ->all();

        $latest = $this->staff()
            ->with('roles:id,name,label')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (User $user) => [
                $user->name,
                $user->roles
                    ->reject(fn ($role) => $role->name === Role::Client->value)
                    ->map(fn ($role) => Role::tryFrom($role->name)?->label() ?? ($role->label ?: $role->name))
                    ->implode(', '),
                $user->created_at?->format('d/m/Y'),
            ])
            ->all();

        return Widgets::section(
            'Accès',
            'Access',
            '/acces',
            [
                Widgets::kpi('staff', 'Comptes du personnel', 'Staff accounts', $staff),
                Widgets::kpi('customers', 'Comptes clients', 'Customer accounts', $customers),
                Widgets::kpi('disabled_accounts', 'Comptes désactivés', 'Disabled accounts', $disabledAccounts, Widgets::NUMBER, $disabledAccounts > 0 ? Widgets::WARN : null),
                Widgets::kpi('switched_off', 'Fonctionnalités coupées', 'Features switched off', $switchedOff, Widgets::NUMBER, $switchedOff > 0 ? Widgets::WARN : null),
                Widgets::kpi('custom_groups', 'Groupes personnalisés', 'Custom groups', $customGroups),
            ],
            [
                Widgets::chart('staff_by_role', 'Personnel par rôle', 'Staff by role', 'donut', $byRole),
            ],
            [
                Widgets::table('latest_staff', 'Derniers comptes du personnel', 'Latest staff accounts', [
                    ['Nom', 'Name'], ['Rôle', 'Role'], ['Créé le', 'Created on'],
                ], $latest),
            ],
        );
    }

    /**
     * @return Builder<User>
     */
    private function staff(): Builder
    {
        return User::query()->whereHas('roles', fn ($query) => $query->where('name', '!=', Role::Client->value));
    }
}
