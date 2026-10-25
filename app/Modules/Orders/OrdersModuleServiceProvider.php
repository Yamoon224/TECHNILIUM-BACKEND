<?php

declare(strict_types=1);

namespace App\Modules\Orders;

use Illuminate\Support\ServiceProvider;

class OrdersModuleServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
