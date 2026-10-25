<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Exceptions\AccountInactiveException;
use App\Modules\Identity\Domain\Exceptions\InvalidCredentialsException;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Support\Facades\Hash;

final class AuthenticateUserAction
{
    /**
     * @return array{user: User, token: string}
     */
    public function execute(string $email, string $password, string $deviceName): array
    {
        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, (string) $user->password)) {
            throw InvalidCredentialsException::create();
        }

        if (! $user->is_active) {
            throw AccountInactiveException::create();
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken($deviceName)->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }
}
