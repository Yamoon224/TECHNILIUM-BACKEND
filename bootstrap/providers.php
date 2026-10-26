<?php

use App\Modules\Catalog\CatalogModuleServiceProvider;
use App\Modules\Identity\IdentityModuleServiceProvider;
use App\Modules\Inventory\InventoryModuleServiceProvider;
use App\Modules\Orders\OrdersModuleServiceProvider;
use App\Modules\Payments\PaymentsModuleServiceProvider;
use App\Modules\Vendors\VendorsModuleServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IdentityModuleServiceProvider::class,
    CatalogModuleServiceProvider::class,
    OrdersModuleServiceProvider::class,
    VendorsModuleServiceProvider::class,
    InventoryModuleServiceProvider::class,
    PaymentsModuleServiceProvider::class,
];
