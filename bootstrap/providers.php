<?php

use App\Modules\Accounting\AccountingModuleServiceProvider;
use App\Modules\Audit\AuditModuleServiceProvider;
use App\Modules\Catalog\CatalogModuleServiceProvider;
use App\Modules\Customers\CustomersModuleServiceProvider;
use App\Modules\Identity\IdentityModuleServiceProvider;
use App\Modules\Inventory\InventoryModuleServiceProvider;
use App\Modules\Invoicing\InvoicingModuleServiceProvider;
use App\Modules\Notifications\NotificationsModuleServiceProvider;
use App\Modules\Orders\OrdersModuleServiceProvider;
use App\Modules\Payments\PaymentsModuleServiceProvider;
use App\Modules\Payouts\PayoutsModuleServiceProvider;
use App\Modules\Procurement\ProcurementModuleServiceProvider;
use App\Modules\Promotions\PromotionsModuleServiceProvider;
use App\Modules\Reporting\ReportingModuleServiceProvider;
use App\Modules\Qr\QrModuleServiceProvider;
use App\Modules\Returns\ReturnsModuleServiceProvider;
use App\Modules\Sales\SalesModuleServiceProvider;
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
    PromotionsModuleServiceProvider::class,
    NotificationsModuleServiceProvider::class,
    AuditModuleServiceProvider::class,
    CustomersModuleServiceProvider::class,
    ProcurementModuleServiceProvider::class,
    QrModuleServiceProvider::class,
    SalesModuleServiceProvider::class,
];
