<?php

declare(strict_types=1);

namespace App\Modules\Reporting;

use Illuminate\Support\ServiceProvider;

class ReportingModuleServiceProvider extends ServiceProvider
{
    // Read-only module: no migrations, and every contract it consumes is
    // bound by the module that owns the data.
    public function register(): void {}

    public function boot(): void {}
}
