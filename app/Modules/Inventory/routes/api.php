<?php

declare(strict_types=1);

use App\Modules\Inventory\Http\Controllers\StockMovementController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/stock')->middleware(['auth:sanctum', 'can:manage-stock'])->group(function () {
    Route::get('/movements', [StockMovementController::class, 'index']);
    Route::post('/movements', [StockMovementController::class, 'store']);
    Route::get('/low-stock', [StockMovementController::class, 'lowStock']);
});
