<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Application\Contracts\UserProvisioningInterface;
use App\Modules\Identity\Application\Gateways\EloquentUserProvisioning;
use App\Modules\Identity\Application\AccessResolver;
use App\Modules\Identity\Domain\AccessCatalogue;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class IdentityModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AccessResolver::class);
        $this->app->bind(UserProvisioningInterface::class, EloquentUserProvisioning::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        // Named abilities other modules gate their routes on (`can:manage-orders`) — they depend on
        // the name only, never on this code. Who holds each one is decided by AccessResolver:
        // the profiles of cahier §4, the custom groups, and the feature switches of the super admin.
        foreach (AccessCatalogue::keys() as $ability) {
            Gate::define($ability, fn (User $user) => app(AccessResolver::class)->allows($user, $ability));
        }

        // Who may switch features and manage groups: the super admin, through the bypass below.
        Gate::define(AccessCatalogue::MANAGE_ACCESS, fn (User $user) => false);

        Gate::before(function (User $user, string $ability) {
            $known = AccessCatalogue::has($ability) || $ability === AccessCatalogue::MANAGE_ACCESS;

            return $known && app(AccessResolver::class)->isSuperAdmin($user) ? true : null;
        });
    }
}
