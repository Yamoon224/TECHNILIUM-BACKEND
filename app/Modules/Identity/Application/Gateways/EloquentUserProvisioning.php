<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Gateways;

use App\Modules\Identity\Application\Contracts\UserProvisioningInterface;
use App\Modules\Identity\Application\Data\UserAccountData;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Support\Facades\Hash;

final class EloquentUserProvisioning implements UserProvisioningInterface
{
    public function createUserWithRole(array $data, Role $role): UserAccountData
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
        ]);

        $user->assignRole($role->value);

        return new UserAccountData(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            phone: $user->phone,
        );
    }
}
