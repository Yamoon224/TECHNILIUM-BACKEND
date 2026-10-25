<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Support\Facades\Hash;

final class RegisterUserAction
{
    /**
     * @param  array{name: string, email: string, phone?: string|null, password: string}  $data
     */
    public function execute(array $data): User
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
        ]);

        $user->assignRole(Role::Client->value);

        return $user;
    }
}
