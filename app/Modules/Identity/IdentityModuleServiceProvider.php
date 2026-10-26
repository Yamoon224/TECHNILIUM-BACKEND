<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Application\Contracts\UserProvisioningInterface;
use App\Modules\Identity\Application\Gateways\EloquentUserProvisioning;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class IdentityModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserProvisioningInterface::class, EloquentUserProvisioning::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        // A named ability other modules can gate routes on (e.g. Orders'
        // `can:manage-orders`) without reaching into Identity's models —
        // they depend on the ability's string name, not this closure.
        //
        // Deliberately narrow (cahier §4): only Opérateur commandes and
        // Administrateur handle order fulfilment. Every other backoffice
        // role — Vendeur tiers especially, an external party — must NOT
        // see or move every order just by being "not a client".
        Gate::define(
            'manage-orders',
            fn (User $user) => $user->hasAnyRole([
                Role::OperateurCommandes->value,
                Role::Administrateur->value,
            ]),
        );

        Gate::define(
            'manage-catalog',
            fn (User $user) => $user->hasAnyRole([
                Role::GestionnaireCatalogue->value,
                Role::Administrateur->value,
            ]),
        );

        // Vendor onboarding is a trust/compliance decision (who gets to sell
        // on the marketplace), kept to Administrateur only — unlike catalog
        // content, it's not delegated to Gestionnaire catalogue.
        Gate::define(
            'manage-vendors',
            fn (User $user) => $user->hasRole(Role::Administrateur->value),
        );

        // A vendor managing their own listings — the vendor's Vendor record
        // being Active is checked separately in the Vendors controllers,
        // which own that data; this gate only confirms the role.
        Gate::define(
            'be-vendor',
            fn (User $user) => $user->hasRole(Role::VendeurTiers->value),
        );

        Gate::define(
            'manage-stock',
            fn (User $user) => $user->hasAnyRole([
                Role::GestionnaireStock->value,
                Role::Administrateur->value,
            ]),
        );

        Gate::define(
            'manage-payments',
            fn (User $user) => $user->hasAnyRole([
                Role::Comptable->value,
                Role::Administrateur->value,
            ]),
        );
    }
}
