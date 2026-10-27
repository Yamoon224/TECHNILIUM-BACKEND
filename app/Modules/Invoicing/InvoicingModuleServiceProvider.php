<?php

declare(strict_types=1);

namespace App\Modules\Invoicing;

use App\Modules\Invoicing\Application\Listeners\CreditInvoiceOnReturnRefund;
use App\Modules\Shared\Events\ReturnRefunded;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class InvoicingModuleServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'invoicing');

        Event::listen(ReturnRefunded::class, CreditInvoiceOnReturnRefund::class);
    }
}
