<?php

declare(strict_types=1);

namespace App\Modules\Promotions;

use App\Modules\Promotions\Application\Contracts\CouponGatewayInterface;
use App\Modules\Promotions\Application\Gateways\EloquentCouponGateway;
use Illuminate\Support\ServiceProvider;

class PromotionsModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CouponGatewayInterface::class, EloquentCouponGateway::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
