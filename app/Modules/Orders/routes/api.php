<?php

declare(strict_types=1);

use App\Modules\Orders\Http\Controllers\BackofficeOrderController;
use App\Modules\Orders\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('orders')->group(function () {
    Route::post('/', [OrderController::class, 'store'])->middleware('throttle:20,1');

    // Must come before /{orderNumber} — otherwise "mine" matches that
    // wildcard first and this route is never reached.
    Route::middleware('auth:sanctum')->get('/mine', [OrderController::class, 'mine']);
    Route::middleware('auth:sanctum')->get('/mine/{orderNumber}', [OrderController::class, 'mineShow']);

    Route::get('/{orderNumber}', [OrderController::class, 'show'])->middleware('throttle:20,1');

    Route::middleware(['auth:sanctum', 'can:manage-orders'])->group(function () {
        Route::get('/', [BackofficeOrderController::class, 'index']);
        Route::post('/{orderNumber}/transition', [BackofficeOrderController::class, 'transition']);
        Route::post('/{orderNumber}/sub-orders/{subOrder}/transition', [BackofficeOrderController::class, 'transitionSubOrder']);
    });
});
