<?php

declare(strict_types=1);

namespace App\Modules\Inventory;

use Illuminate\Support\ServiceProvider;

class InventoryModuleServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
