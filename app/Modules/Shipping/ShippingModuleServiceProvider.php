<?php

declare(strict_types=1);

namespace App\Modules\Shipping;

use App\Modules\Shared\Dashboard\DashboardSection;
use App\Modules\Shipping\Application\Dashboard\ShipmentsSection;
use Illuminate\Support\ServiceProvider;

class ShippingModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([ShipmentsSection::class], DashboardSection::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'shipping');
    }
}
