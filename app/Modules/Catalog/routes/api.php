<?php

declare(strict_types=1);

use App\Modules\Catalog\Http\Controllers\Admin\CategoryAdminController;
use App\Modules\Catalog\Http\Controllers\Admin\OfferModerationController;
use App\Modules\Catalog\Http\Controllers\Admin\ProductAdminController;
use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('catalog')->group(function () {
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'can:manage-catalog'])->group(function () {
    Route::get('/categories', [CategoryAdminController::class, 'index']);
    Route::post('/categories', [CategoryAdminController::class, 'store']);
    Route::put('/categories/{category}', [CategoryAdminController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryAdminController::class, 'destroy']);

    Route::get('/products', [ProductAdminController::class, 'index']);
    Route::post('/products', [ProductAdminController::class, 'store']);
    Route::put('/products/{product}', [ProductAdminController::class, 'update']);
    Route::delete('/products/{product}', [ProductAdminController::class, 'destroy']);

    Route::get('/offers', [OfferModerationController::class, 'index']);
    Route::post('/offers/{offer}/approve', [OfferModerationController::class, 'approve']);
    Route::post('/offers/{offer}/reject', [OfferModerationController::class, 'reject']);
});
