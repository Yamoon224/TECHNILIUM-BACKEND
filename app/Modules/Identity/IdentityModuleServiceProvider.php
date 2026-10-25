<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use Illuminate\Support\ServiceProvider;

class IdentityModuleServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
