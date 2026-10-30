<?php

declare(strict_types=1);

namespace App\Modules\Payments;

use App\Modules\Payments\Application\Contracts\PaymentLookupInterface;
use App\Modules\Payments\Application\Contracts\PaymentStatsInterface;
use App\Modules\Payments\Application\Gateways\EloquentPaymentLookup;
use App\Modules\Payments\Application\Gateways\EloquentPaymentStats;
use App\Modules\Payments\Application\Listeners\RefundOnReturn;
use App\Modules\Payments\Application\Dashboard\PaymentsSection;
use App\Modules\Shared\Dashboard\DashboardSection;
use App\Modules\Shared\Events\ReturnRefunded;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class PaymentsModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentStatsInterface::class, EloquentPaymentStats::class);
        $this->app->bind(PaymentLookupInterface::class, EloquentPaymentLookup::class);
        $this->app->tag([PaymentsSection::class], DashboardSection::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'payments');

        Event::listen(ReturnRefunded::class, RefundOnReturn::class);
    }
}
