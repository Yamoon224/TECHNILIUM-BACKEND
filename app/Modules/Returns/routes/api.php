<?php

declare(strict_types=1);

use App\Modules\Returns\Http\Controllers\ReturnController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/returns')->middleware(['auth:sanctum', 'can:manage-returns'])->group(function () {
    Route::get('/', [ReturnController::class, 'index']);
    Route::post('/', [ReturnController::class, 'store']);
    Route::post('/{return}/accept', [ReturnController::class, 'accept']);
    Route::post('/{return}/reject', [ReturnController::class, 'reject']);
    Route::post('/{return}/receive', [ReturnController::class, 'receive']);
    Route::post('/{return}/refund', [ReturnController::class, 'refund']);
});
