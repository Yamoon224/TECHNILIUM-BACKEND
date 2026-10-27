<?php

declare(strict_types=1);

use App\Modules\Payouts\Http\Controllers\PayoutController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/payouts')->middleware(['auth:sanctum', 'can:manage-payouts'])->group(function () {
    Route::get('/', [PayoutController::class, 'index']);
    Route::post('/', [PayoutController::class, 'store']);
    Route::get('/{payout}', [PayoutController::class, 'show']);
    Route::post('/{payout}/pay', [PayoutController::class, 'pay']);
});
