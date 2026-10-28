<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\AccessResolver;
use App\Modules\Identity\Domain\Exceptions\AccessException;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\FeatureOverride;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * Custom user groups. The built-in profiles of the cahier §4 are never edited or deleted here:
 * what they may do is fixed, and the super admin narrows it with feature switches instead.
 */
final class ManageAccessGroupsAction
{
    public function __construct(private readonly AccessResolver $access) {}

    /**
     * @param  list<string>  $permissions
     */
    public function create(string $label, ?string $description, array $permissions): RoleModel
    {
        return DB::transaction(function () use ($label, $description, $permissions) {
            $role = RoleModel::create(['name' => $this->uniqueName($label), 'guard_name' => 'web']);
            $role->forceFill(['label' => $label, 'description' => $description])->save();

            $this->grant($role, $permissions);

            return $role->refresh();
        });
    }

    /**
     * @param  list<string>  $permissions
     */
    public function update(string $name, string $label, ?string $description, array $permissions): RoleModel
    {
        $role = $this->customGroup($name);

        DB::transaction(function () use ($role, $label, $description, $permissions) {
            $role->forceFill(['label' => $label, 'description' => $description])->save();
            $this->grant($role, $permissions);
        });

        return $role->refresh();
    }

    public function delete(string $name): void
    {
        $role = $this->customGroup($name);

        if ($role->users()->exists()) {
            throw AccessException::groupInUse();
        }

        DB::transaction(function () use ($role) {
            FeatureOverride::query()->where('subject_type', FeatureOverride::ROLE)->where('subject', $role->name)->delete();
            $role->delete();
        });

        $this->access->forget();
    }

    private function customGroup(string $name): RoleModel
    {
        if (Role::tryFrom($name) !== null) {
            throw AccessException::builtinGroup();
        }

        return RoleModel::query()->where('name', $name)->where('guard_name', 'web')->firstOrFail();
    }

    /**
     * @param  list<string>  $abilities
     */
    private function grant(RoleModel $role, array $abilities): void
    {
        $permissions = array_map(fn (string $ability) => Permission::findOrCreate($ability, 'web'), array_values(array_unique($abilities)));

        $role->syncPermissions($permissions);
        $this->access->forget();
    }

    private function uniqueName(string $label): string
    {
        $base = 'groupe_'.(Str::slug($label, '_') ?: 'sans_nom');
        $name = $base;
        $suffix = 2;

        while (RoleModel::query()->where('name', $name)->where('guard_name', 'web')->exists()) {
            $name = $base.'_'.$suffix++;
        }

        return $name;
    }
}
