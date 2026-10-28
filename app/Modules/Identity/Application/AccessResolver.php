<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\AccessCatalogue;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\FeatureOverride;
use App\Modules\Identity\Infrastructure\Models\User;

/**
 * Decides what an account may do, in one place, for the gates and for what the interface shows.
 *
 *  1. The super admin may do everything and cannot be switched off.
 *  2. A feature that is switched off for the user — or for one of their groups — is refused, and the
 *     interface does not show it at all. A switch on the user wins over the switches of their groups,
 *     so one person can be given back what their group lost, or lose what it keeps.
 *  3. Otherwise the feature is allowed to the built-in profiles listed in AccessCatalogue and to the
 *     custom groups the permission was ticked for.
 *
 * Answers are kept for the request; every write goes through forget().
 */
final class AccessResolver
{
    /** @var array<int, list<string>> */
    private array $disabled = [];

    /** @var array<string, bool> */
    private array $granted = [];

    public function isSuperAdmin(User $user): bool
    {
        return $user->hasRole(Role::SuperAdmin->value);
    }

    public function allows(User $user, string $ability): bool
    {
        if (! AccessCatalogue::has($ability)) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if (in_array($ability, $this->disabledFeaturesFor($user), true)) {
            return false;
        }

        $key = $user->id.'|'.$ability;

        return $this->granted[$key] ??= $user->hasAnyRole(AccessCatalogue::builtinRolesFor($ability))
            || $user->checkPermissionTo($ability, 'web');
    }

    /**
     * Features switched off for this user.
     *
     * @return list<string>
     */
    public function disabledFeaturesFor(User $user): array
    {
        if ($this->isSuperAdmin($user)) {
            return [];
        }

        if (isset($this->disabled[$user->id])) {
            return $this->disabled[$user->id];
        }

        $roles = $user->getRoleNames()->all();

        $rows = FeatureOverride::query()
            ->where(function ($query) use ($user, $roles) {
                $query->where(fn ($q) => $q->where('subject_type', FeatureOverride::USER)->where('subject', (string) $user->id))
                    ->orWhere(fn ($q) => $q->where('subject_type', FeatureOverride::ROLE)->whereIn('subject', $roles));
            })
            ->get();

        $off = [];

        foreach (AccessCatalogue::keys() as $feature) {
            $own = $rows->first(fn (FeatureOverride $row) => $row->feature === $feature && $row->subject_type === FeatureOverride::USER);

            if ($own !== null) {
                $disabled = ! $own->enabled;
            } else {
                $disabled = $rows->contains(fn (FeatureOverride $row) => $row->feature === $feature && $row->subject_type === FeatureOverride::ROLE && ! $row->enabled);
            }

            if ($disabled) {
                $off[] = $feature;
            }
        }

        return $this->disabled[$user->id] = $off;
    }

    /**
     * Every feature this user can use: what the interface may show.
     *
     * @return list<string>
     */
    public function abilitiesFor(User $user): array
    {
        return array_values(array_filter(AccessCatalogue::keys(), fn (string $ability) => $this->allows($user, $ability)));
    }

    public function forget(): void
    {
        $this->disabled = [];
        $this->granted = [];
    }
}
