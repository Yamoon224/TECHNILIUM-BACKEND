<?php

declare(strict_types=1);

namespace App\Modules\Inventory;

use App\Modules\Inventory\Application\Dashboard\StockSection;
use App\Modules\Inventory\Application\Listeners\RestockReturnedItems;
use App\Modules\Shared\Dashboard\DashboardSection;
use App\Modules\Inventory\Application\Listeners\StockPurchasedGoods;
use App\Modules\Shared\Events\PurchaseReceived;
use App\Modules\Shared\Events\ReturnReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class InventoryModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([StockSection::class], DashboardSection::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        Event::listen(ReturnReceived::class, RestockReturnedItems::class);
        Event::listen(PurchaseReceived::class, StockPurchasedGoods::class);
    }
}
