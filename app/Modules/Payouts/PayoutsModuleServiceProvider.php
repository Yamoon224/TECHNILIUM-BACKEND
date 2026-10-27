<?php

declare(strict_types=1);

namespace App\Modules\Payouts;

use App\Modules\Payouts\Application\Contracts\PayoutStatsInterface;
use App\Modules\Payouts\Application\Contracts\VendorPayoutReaderInterface;
use App\Modules\Payouts\Application\Gateways\EloquentPayoutStats;
use App\Modules\Payouts\Application\Gateways\EloquentVendorPayoutReader;
use Illuminate\Support\ServiceProvider;

class PayoutsModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(VendorPayoutReaderInterface::class, EloquentVendorPayoutReader::class);
        $this->app->bind(PayoutStatsInterface::class, EloquentPayoutStats::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
