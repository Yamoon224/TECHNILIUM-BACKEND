<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Infrastructure\Models\User;

final class LogoutUserAction
{
    public function execute(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
