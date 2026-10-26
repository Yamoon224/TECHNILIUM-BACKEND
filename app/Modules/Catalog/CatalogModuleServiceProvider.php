<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use App\Modules\Catalog\Application\Contracts\CheckoutGatewayInterface;
use App\Modules\Catalog\Application\Contracts\VendorOfferGatewayInterface;
use App\Modules\Catalog\Application\Gateways\EloquentCheckoutGateway;
use App\Modules\Catalog\Application\Gateways\EloquentVendorOfferGateway;
use Illuminate\Support\ServiceProvider;

class CatalogModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CheckoutGatewayInterface::class, EloquentCheckoutGateway::class);
        $this->app->bind(VendorOfferGatewayInterface::class, EloquentVendorOfferGateway::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
