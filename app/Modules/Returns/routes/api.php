<?php

declare(strict_types=1);

use App\Modules\Returns\Http\Controllers\CustomerReturnController;
use App\Modules\Returns\Http\Controllers\ReturnController;
use Illuminate\Support\Facades\Route;

Route::prefix('customer')->middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
    Route::get('/orders/{orderNumber}/returns', [CustomerReturnController::class, 'forOrder']);
    Route::post('/returns', [CustomerReturnController::class, 'store']);
});

Route::prefix('admin/returns')->middleware(['auth:sanctum', 'can:manage-returns'])->group(function () {
    Route::get('/', [ReturnController::class, 'index']);
    Route::post('/', [ReturnController::class, 'store']);
    Route::post('/{return}/accept', [ReturnController::class, 'accept']);
    Route::post('/{return}/reject', [ReturnController::class, 'reject']);
    Route::post('/{return}/receive', [ReturnController::class, 'receive']);
    Route::post('/{return}/refund', [ReturnController::class, 'refund']);
});
