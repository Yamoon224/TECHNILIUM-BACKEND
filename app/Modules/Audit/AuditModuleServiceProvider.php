<?php

declare(strict_types=1);

namespace App\Modules\Audit;

use App\Modules\Audit\Application\Dashboard\AuditSection;
use App\Modules\Shared\Dashboard\DashboardSection;
use Illuminate\Support\ServiceProvider;

class AuditModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([AuditSection::class], DashboardSection::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
