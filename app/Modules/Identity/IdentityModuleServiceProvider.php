<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class IdentityModuleServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        // A named ability other modules can gate routes on (e.g. Orders'
        // `can:manage-orders`) without reaching into Identity's models —
        // they depend on the ability's string name, not this closure.
        Gate::define(
            'manage-orders',
            fn (User $user) => $user->hasAnyRole(array_map(
                fn (Role $role) => $role->value,
                Role::backofficeRoles(),
            )),
        );
    }
}
