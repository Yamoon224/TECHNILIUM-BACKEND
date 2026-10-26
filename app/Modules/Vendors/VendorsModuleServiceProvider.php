<?php

declare(strict_types=1);

namespace App\Modules\Vendors;

use Illuminate\Support\ServiceProvider;

class VendorsModuleServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
