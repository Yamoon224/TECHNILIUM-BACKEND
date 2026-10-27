<?php

declare(strict_types=1);

namespace App\Modules\Returns;

use App\Modules\Returns\Application\Contracts\RefundLookupInterface;
use App\Modules\Returns\Application\Gateways\EloquentRefundLookup;
use Illuminate\Support\ServiceProvider;

class ReturnsModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RefundLookupInterface::class, EloquentRefundLookup::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
