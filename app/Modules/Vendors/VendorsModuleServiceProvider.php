<?php

declare(strict_types=1);

namespace App\Modules\Vendors;

use App\Modules\Shared\Dashboard\DashboardSection;
use App\Modules\Vendors\Application\Dashboard\VendorsSection;
use Illuminate\Support\ServiceProvider;

class VendorsModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([VendorsSection::class], DashboardSection::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
