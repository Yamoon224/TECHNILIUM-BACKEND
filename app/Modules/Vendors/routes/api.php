<?php

declare(strict_types=1);

use App\Modules\Vendors\Http\Controllers\Admin\VendorAdminController;
use App\Modules\Vendors\Http\Controllers\VendorController;
use App\Modules\Vendors\Http\Controllers\VendorOfferController;
use Illuminate\Support\Facades\Route;

Route::prefix('vendors')->group(function () {
    Route::post('/apply', [VendorController::class, 'apply'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'can:be-vendor'])->prefix('me')->group(function () {
        Route::get('/offers', [VendorOfferController::class, 'index']);
        Route::post('/offers', [VendorOfferController::class, 'store']);
        Route::put('/offers/{offer}', [VendorOfferController::class, 'update']);
        Route::delete('/offers/{offer}', [VendorOfferController::class, 'destroy']);
    });
});

Route::prefix('admin/vendors')->middleware(['auth:sanctum', 'can:manage-vendors'])->group(function () {
    Route::get('/', [VendorAdminController::class, 'index']);
    Route::post('/{vendor}/approve', [VendorAdminController::class, 'approve']);
    Route::post('/{vendor}/suspend', [VendorAdminController::class, 'suspend']);
    Route::post('/{vendor}/reactivate', [VendorAdminController::class, 'reactivate']);
});
