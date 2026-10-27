<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Modules\Notifications\Application\Listeners\NotifyOnOrderEvents;
use App\Modules\Shared\Events\OrderPlaced;
use App\Modules\Shared\Events\OrderStatusChanged;
use App\Modules\Shared\Events\ReturnRefunded;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class NotificationsModuleServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        Event::listen(OrderPlaced::class, [NotifyOnOrderEvents::class, 'onOrderPlaced']);
        Event::listen(OrderStatusChanged::class, [NotifyOnOrderEvents::class, 'onOrderStatusChanged']);
        Event::listen(ReturnRefunded::class, [NotifyOnOrderEvents::class, 'onReturnRefunded']);
    }
}
