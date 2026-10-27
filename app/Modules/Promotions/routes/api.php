<?php

declare(strict_types=1);

use App\Modules\Promotions\Http\Controllers\CouponAdminController;
use App\Modules\Promotions\Http\Controllers\CouponQuoteController;
use Illuminate\Support\Facades\Route;

Route::post('coupons/quote', CouponQuoteController::class)->middleware('throttle:30,1');

Route::prefix('admin/coupons')->middleware(['auth:sanctum', 'can:manage-promotions'])->group(function () {
    Route::get('/', [CouponAdminController::class, 'index']);
    Route::post('/', [CouponAdminController::class, 'store']);
    Route::post('/{coupon}/activate', [CouponAdminController::class, 'activate']);
    Route::post('/{coupon}/deactivate', [CouponAdminController::class, 'deactivate']);
});
