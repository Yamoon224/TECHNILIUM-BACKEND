<?php

declare(strict_types=1);

namespace App\Modules\Orders;

use App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface;
use App\Modules\Orders\Application\Gateways\EloquentOrderLookupGateway;
use Illuminate\Support\ServiceProvider;

class OrdersModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrderLookupGatewayInterface::class, EloquentOrderLookupGateway::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
