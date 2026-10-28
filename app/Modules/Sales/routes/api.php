<?php

declare(strict_types=1);

use App\Modules\Sales\Http\Controllers\CashClosingController;
use App\Modules\Sales\Http\Controllers\InternalSaleController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/internal-sales')->middleware(['auth:sanctum', 'can:manage-internal-sales', 'throttle:120,1'])->group(function () {
    Route::get('/', [InternalSaleController::class, 'index']);
    Route::post('/', [InternalSaleController::class, 'store']);
    Route::get('/{orderNumber}/receipt', [InternalSaleController::class, 'receipt']);
    Route::post('/{orderNumber}/cancel', [InternalSaleController::class, 'cancel']);
});

Route::prefix('admin/cash-closings')->middleware(['auth:sanctum', 'can:manage-internal-sales', 'throttle:60,1'])->group(function () {
    Route::get('/', [CashClosingController::class, 'index']);
    Route::get('/current', [CashClosingController::class, 'current']);
    Route::post('/', [CashClosingController::class, 'store']);
    Route::get('/{closing}/pdf', [CashClosingController::class, 'pdf']);
});
