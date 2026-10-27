<?php

declare(strict_types=1);

namespace App\Modules\Payments;

use App\Modules\Payments\Application\Contracts\PaymentStatsInterface;
use App\Modules\Payments\Application\Gateways\EloquentPaymentStats;
use App\Modules\Payments\Application\Listeners\RefundOnReturn;
use App\Modules\Shared\Events\ReturnRefunded;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class PaymentsModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentStatsInterface::class, EloquentPaymentStats::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        Event::listen(ReturnRefunded::class, RefundOnReturn::class);
    }
}
