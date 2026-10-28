<?php

declare(strict_types=1);

namespace App\Modules\Qr;

use Illuminate\Support\ServiceProvider;

/**
 * QR codes that identify a product, an order, an invoice or a supply. The image itself is drawn in
 * the back office (with the logo at its centre); this module decides what the code says and who may
 * ask for it.
 */
class QrModuleServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void {}
}
