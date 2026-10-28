<?php

declare(strict_types=1);

/**
 * Root API entry point. Laravel wraps this file with the `api` middleware
 * group and the `/api` prefix (see bootstrap/app.php). Every module owns its
 * own routes/api.php; this file only aggregates them under /api/v1 so a
 * module's internal route layout never leaks into the URL.
 */

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    require base_path('app/Modules/Identity/routes/api.php');
    require base_path('app/Modules/Catalog/routes/api.php');
    require base_path('app/Modules/Orders/routes/api.php');
    require base_path('app/Modules/Vendors/routes/api.php');
    require base_path('app/Modules/Inventory/routes/api.php');
    require base_path('app/Modules/Payments/routes/api.php');
    require base_path('app/Modules/Shipping/routes/api.php');
    require base_path('app/Modules/Invoicing/routes/api.php');
    require base_path('app/Modules/Accounting/routes/api.php');
    require base_path('app/Modules/Payouts/routes/api.php');
    require base_path('app/Modules/Returns/routes/api.php');
    require base_path('app/Modules/Reporting/routes/api.php');
    require base_path('app/Modules/Promotions/routes/api.php');
    require base_path('app/Modules/Notifications/routes/api.php');
    require base_path('app/Modules/Audit/routes/api.php');
    require base_path('app/Modules/Customers/routes/api.php');
    require base_path('app/Modules/Procurement/routes/api.php');
    require base_path('app/Modules/Qr/routes/api.php');
});
