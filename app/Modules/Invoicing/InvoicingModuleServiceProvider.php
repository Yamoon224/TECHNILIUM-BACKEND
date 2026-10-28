<?php

declare(strict_types=1);

namespace App\Modules\Invoicing;

use App\Modules\Invoicing\Application\Listeners\CreditInvoiceOnReturnRefund;
use App\Modules\Invoicing\Application\Listeners\EmailInvoiceToCustomer;
use App\Modules\Invoicing\Application\Listeners\IssueInvoiceOnConfirmation;
use App\Modules\Shared\Events\InvoiceIssued;
use App\Modules\Shared\Events\OrderStatusChanged;
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

        Event::listen(OrderStatusChanged::class, IssueInvoiceOnConfirmation::class);
        Event::listen(InvoiceIssued::class, EmailInvoiceToCustomer::class);
        Event::listen(ReturnRefunded::class, CreditInvoiceOnReturnRefund::class);
    }
}
