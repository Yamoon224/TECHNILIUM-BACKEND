<?php

declare(strict_types=1);

namespace App\Modules\Procurement;

use App\Modules\Procurement\Application\Contracts\OfferCostInterface;
use App\Modules\Procurement\Application\Gateways\EloquentOfferCost;
use App\Modules\Procurement\Application\Listeners\RecognizeCostOfGoodsSold;
use App\Modules\Shared\Events\OrderStatusChanged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ProcurementModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OfferCostInterface::class, EloquentOfferCost::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        Event::listen(OrderStatusChanged::class, RecognizeCostOfGoodsSold::class);
    }
}
