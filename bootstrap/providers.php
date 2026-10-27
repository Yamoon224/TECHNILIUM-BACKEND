<?php

use App\Modules\Accounting\AccountingModuleServiceProvider;
use App\Modules\Catalog\CatalogModuleServiceProvider;
use App\Modules\Identity\IdentityModuleServiceProvider;
use App\Modules\Inventory\InventoryModuleServiceProvider;
use App\Modules\Invoicing\InvoicingModuleServiceProvider;
use App\Modules\Orders\OrdersModuleServiceProvider;
use App\Modules\Payments\PaymentsModuleServiceProvider;
use App\Modules\Payouts\PayoutsModuleServiceProvider;
use App\Modules\Reporting\ReportingModuleServiceProvider;
use App\Modules\Returns\ReturnsModuleServiceProvider;
use App\Modules\Shipping\ShippingModuleServiceProvider;
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
    ShippingModuleServiceProvider::class,
    InvoicingModuleServiceProvider::class,
    AccountingModuleServiceProvider::class,
    PayoutsModuleServiceProvider::class,
    ReturnsModuleServiceProvider::class,
    ReportingModuleServiceProvider::class,
];
