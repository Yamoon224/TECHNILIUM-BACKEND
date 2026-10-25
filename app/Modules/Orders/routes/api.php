<?php

declare(strict_types=1);

use App\Modules\Orders\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('orders')->group(function () {
    Route::post('/', [OrderController::class, 'store'])->middleware('throttle:20,1');
    Route::get('/{orderNumber}', [OrderController::class, 'show']);
});
