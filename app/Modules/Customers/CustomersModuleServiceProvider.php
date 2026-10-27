<?php

declare(strict_types=1);

namespace App\Modules\Customers;

use App\Modules\Customers\Domain\TicketSla;
use Illuminate\Support\ServiceProvider;

class CustomersModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            TicketSla::class,
            fn () => new TicketSla((array) config('support.first_response_hours')),
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
