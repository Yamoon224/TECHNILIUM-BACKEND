<?php

declare(strict_types=1);

namespace App\Modules\Reporting;

use App\Modules\Reporting\Application\Dashboard\AlertsSection;
use App\Modules\Reporting\Application\Dashboard\DashboardService;
use App\Modules\Reporting\Application\Dashboard\FinanceSection;
use App\Modules\Reporting\Application\Dashboard\SalesSection;
use App\Modules\Reporting\Application\ReportingService;
use App\Modules\Shared\Dashboard\DashboardSection;
use Illuminate\Support\ServiceProvider;

class ReportingModuleServiceProvider extends ServiceProvider
{
    // Read-only module: no migrations, and every contract it consumes is
    // bound by the module that owns the data.
    public function register(): void
    {
        // One instance per request: the management blocks share a single period summary.
        $this->app->singleton(ReportingService::class);

        // The management blocks; every other module tags its own.
        $this->app->tag([SalesSection::class, FinanceSection::class, AlertsSection::class], DashboardSection::class);

        $this->app->when(DashboardService::class)->needs('$sections')->giveTagged(DashboardSection::class);
    }

    public function boot(): void {}
}
