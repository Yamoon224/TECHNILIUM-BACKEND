<?php

declare(strict_types=1);

use App\Modules\Invoicing\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/invoices')->middleware(['auth:sanctum', 'can:manage-invoices'])->group(function () {
    Route::get('/', [InvoiceController::class, 'index']);
    Route::post('/', [InvoiceController::class, 'store']);
    Route::get('/{invoice}', [InvoiceController::class, 'show']);
    Route::post('/{invoice}/credit-note', [InvoiceController::class, 'creditNote']);
});
