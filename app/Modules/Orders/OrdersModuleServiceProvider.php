<?php

declare(strict_types=1);

namespace App\Modules\Orders;

use App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface;
use App\Modules\Orders\Application\Contracts\OrderStatsInterface;
use App\Modules\Orders\Application\Gateways\EloquentOrderLookupGateway;
use App\Modules\Orders\Application\Dashboard\OrdersSection;
use App\Modules\Orders\Application\Gateways\EloquentOrderStats;
use App\Modules\Shared\Dashboard\DashboardSection;
use Illuminate\Support\ServiceProvider;

class OrdersModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrderLookupGatewayInterface::class, EloquentOrderLookupGateway::class);
        $this->app->bind(OrderStatsInterface::class, EloquentOrderStats::class);
        $this->app->tag([OrdersSection::class], DashboardSection::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
