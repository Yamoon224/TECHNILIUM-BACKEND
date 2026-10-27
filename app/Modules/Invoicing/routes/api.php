<?php

declare(strict_types=1);

use App\Modules\Invoicing\Http\Controllers\CustomerInvoiceController;
use App\Modules\Invoicing\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::prefix('customer')->middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('/orders/{orderNumber}/invoices', [CustomerInvoiceController::class, 'forOrder']);
    Route::get('/invoices/{invoice}/pdf', [CustomerInvoiceController::class, 'pdf']);
});

Route::prefix('admin/invoices')->middleware(['auth:sanctum', 'can:manage-invoices'])->group(function () {
    Route::get('/', [InvoiceController::class, 'index']);
    Route::post('/', [InvoiceController::class, 'store']);
    Route::get('/{invoice}', [InvoiceController::class, 'show']);
    Route::get('/{invoice}/pdf', [InvoiceController::class, 'pdf']);
    Route::post('/{invoice}/credit-note', [InvoiceController::class, 'creditNote']);
});
