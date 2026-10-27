<?php

declare(strict_types=1);

namespace App\Modules\Accounting;

use App\Modules\Accounting\Application\Listeners\PostInvoiceEntry;
use App\Modules\Accounting\Application\Listeners\PostPaymentEntry;
use App\Modules\Shared\Events\InvoiceIssued;
use App\Modules\Shared\Events\PaymentSettled;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AccountingModuleServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        // Synchronous on purpose: the entry is written in the same
        // transaction as the business fact ("aucune commande payée sans
        // écriture", cahier §2.2).
        Event::listen(InvoiceIssued::class, PostInvoiceEntry::class);
        Event::listen(PaymentSettled::class, PostPaymentEntry::class);
    }
}
