<?php

declare(strict_types=1);

namespace App\Modules\Returns;

use App\Modules\Returns\Application\Contracts\RefundLookupInterface;
use App\Modules\Returns\Application\Contracts\ReturnStatsInterface;
use App\Modules\Returns\Application\Gateways\EloquentRefundLookup;
use App\Modules\Returns\Application\Gateways\EloquentReturnStats;
use Illuminate\Support\ServiceProvider;

class ReturnsModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RefundLookupInterface::class, EloquentRefundLookup::class);
        $this->app->bind(ReturnStatsInterface::class, EloquentReturnStats::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
