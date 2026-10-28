<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Seeders;

use App\Modules\Identity\Domain\Role as RoleEnum;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class IdentitySeeder extends Seeder
{
    public function run(): void
    {
        foreach (RoleEnum::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@technilium.app'],
            [
                'name' => 'Admin Technilium',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        if (! $admin->hasRole(RoleEnum::Administrateur->value)) {
            $admin->assignRole(RoleEnum::Administrateur->value);
        }

        // The only account that manages access (feature switches, groups). Change its password at once.
        $super = User::query()->firstOrCreate(
            ['email' => 'superadmin@technilium.app'],
            [
                'name' => 'Super Admin Technilium',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        if (! $super->hasRole(RoleEnum::SuperAdmin->value)) {
            $super->assignRole(RoleEnum::SuperAdmin->value);
        }
    }
}
