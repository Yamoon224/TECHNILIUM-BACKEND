<?php

declare(strict_types=1);

use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('catalog')->group(function () {
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);
});
