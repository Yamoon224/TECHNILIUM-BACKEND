<?php

declare(strict_types=1);

namespace App\Modules\Payouts;

use App\Modules\Payouts\Application\Contracts\VendorPayoutReaderInterface;
use App\Modules\Payouts\Application\Gateways\EloquentVendorPayoutReader;
use Illuminate\Support\ServiceProvider;

class PayoutsModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(VendorPayoutReaderInterface::class, EloquentVendorPayoutReader::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
