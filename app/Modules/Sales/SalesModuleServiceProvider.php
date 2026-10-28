<?php

declare(strict_types=1);

namespace App\Modules\Sales;

use Illuminate\Support\ServiceProvider;

/**
 * Internal (counter) sales: a sale rung up by staff in the shop or over the phone. It is a real
 * order — stock, invoice, ledger and cost of sales all follow — but it is paid and handed over on
 * the spot, so it ends with a payment receipt. This module only orchestrates Orders and Payments.
 */
class SalesModuleServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'sales');
    }
}
