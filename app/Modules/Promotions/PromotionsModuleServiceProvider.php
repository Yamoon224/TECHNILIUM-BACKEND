<?php

declare(strict_types=1);

namespace App\Modules\Promotions;

use App\Modules\Promotions\Application\Contracts\CouponGatewayInterface;
use App\Modules\Promotions\Application\Gateways\EloquentCouponGateway;
use App\Modules\Promotions\Application\Dashboard\PromotionsSection;
use App\Modules\Shared\Dashboard\DashboardSection;
use Illuminate\Support\ServiceProvider;

class PromotionsModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CouponGatewayInterface::class, EloquentCouponGateway::class);

        $this->app->tag([PromotionsSection::class], DashboardSection::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
