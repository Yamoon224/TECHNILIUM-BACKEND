<?php

declare(strict_types=1);

use App\Modules\Promotions\Http\Controllers\AnnouncementAdminController;
use App\Modules\Promotions\Http\Controllers\AnnouncementController;
use App\Modules\Promotions\Http\Controllers\CouponAdminController;
use App\Modules\Promotions\Http\Controllers\CouponQuoteController;
use Illuminate\Support\Facades\Route;

Route::post('coupons/quote', CouponQuoteController::class)->middleware('throttle:30,1');

Route::get('announcements', [AnnouncementController::class, 'index']);

Route::prefix('admin/announcements')->middleware(['auth:sanctum', 'can:manage-promotions'])->group(function () {
    Route::get('/', [AnnouncementAdminController::class, 'index']);
    Route::post('/', [AnnouncementAdminController::class, 'store']);
    // POST (not PUT): a multipart body is only parsed on POST.
    Route::post('/{announcement}', [AnnouncementAdminController::class, 'update']);
    Route::post('/{announcement}/archive', [AnnouncementAdminController::class, 'archive']);
    Route::post('/{announcement}/restore', [AnnouncementAdminController::class, 'restore']);
    Route::delete('/{announcement}', [AnnouncementAdminController::class, 'destroy']);
});

Route::prefix('admin/coupons')->middleware(['auth:sanctum', 'can:manage-promotions'])->group(function () {
    Route::get('/', [CouponAdminController::class, 'index']);
    Route::post('/', [CouponAdminController::class, 'store']);
    Route::post('/{coupon}/activate', [CouponAdminController::class, 'activate']);
    Route::post('/{coupon}/deactivate', [CouponAdminController::class, 'deactivate']);
});
