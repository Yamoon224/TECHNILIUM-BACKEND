<?php

declare(strict_types=1);

use App\Modules\Payments\Http\Controllers\CustomerReceiptController;
use App\Modules\Payments\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('customer')->middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('/orders/{orderNumber}/receipts', [CustomerReceiptController::class, 'forOrder']);
    Route::get('/payments/{payment}/receipt', [CustomerReceiptController::class, 'pdf']);
});

Route::prefix('admin/payments')->middleware(['auth:sanctum', 'can:manage-payments'])->group(function () {
    Route::get('/', [PaymentController::class, 'index']);
    Route::post('/', [PaymentController::class, 'store']);
    Route::get('/{payment}/receipt', [PaymentController::class, 'receipt']);
    Route::post('/{payment}/confirm', [PaymentController::class, 'confirm']);
    Route::post('/{payment}/fail', [PaymentController::class, 'fail']);
    Route::post('/{payment}/refund', [PaymentController::class, 'refund']);
});
