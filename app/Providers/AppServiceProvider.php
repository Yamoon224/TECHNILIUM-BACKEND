<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Flat JSON responses (no "data" envelope) across every module's API Resources.
        JsonResource::withoutWrapping();

        // Fail loudly on unguarded mass-assignment/lazy-loading during dev and tests.
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
