<?php

declare(strict_types=1);

namespace App\Modules\Shipping;

use Illuminate\Support\ServiceProvider;

class ShippingModuleServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'shipping');
    }
}
