<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use App\Modules\Catalog\Application\Contracts\CheckoutGatewayInterface;
use App\Modules\Catalog\Application\Contracts\InventoryGatewayInterface;
use App\Modules\Catalog\Application\Contracts\ProductLookupInterface;
use App\Modules\Catalog\Application\Contracts\VendorOfferGatewayInterface;
use App\Modules\Catalog\Application\Gateways\EloquentCheckoutGateway;
use App\Modules\Catalog\Application\Gateways\EloquentInventoryGateway;
use App\Modules\Catalog\Application\Gateways\EloquentProductLookup;
use App\Modules\Catalog\Application\Gateways\EloquentVendorOfferGateway;
use App\Modules\Catalog\Application\Dashboard\CatalogSection;
use App\Modules\Shared\Dashboard\DashboardSection;
use Illuminate\Support\ServiceProvider;

class CatalogModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CheckoutGatewayInterface::class, EloquentCheckoutGateway::class);
        $this->app->bind(VendorOfferGatewayInterface::class, EloquentVendorOfferGateway::class);
        $this->app->bind(InventoryGatewayInterface::class, EloquentInventoryGateway::class);
        $this->app->bind(ProductLookupInterface::class, EloquentProductLookup::class);

        $this->app->tag([CatalogSection::class], DashboardSection::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
