<?php

declare(strict_types=1);

use App\Modules\Procurement\Http\Controllers\ProcurementReportController;
use App\Modules\Procurement\Http\Controllers\PurchaseOrderController;
use App\Modules\Procurement\Http\Controllers\SupplierController;
use App\Modules\Procurement\Http\Controllers\SupplierInvoiceController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth:sanctum', 'can:manage-procurement'])->group(function () {
    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::post('/suppliers', [SupplierController::class, 'store']);
    Route::patch('/suppliers/{supplier}', [SupplierController::class, 'update']);

    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store']);
    Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show']);
    Route::post('/purchase-orders/{purchaseOrder}/place', [PurchaseOrderController::class, 'place']);
    Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel']);
    Route::post('/purchase-orders/{purchaseOrder}/landed-costs', [PurchaseOrderController::class, 'landedCost']);
    Route::post('/purchase-orders/{purchaseOrder}/receipts', [PurchaseOrderController::class, 'receive']);

    Route::get('/supplier-invoices', [SupplierInvoiceController::class, 'index']);
    Route::post('/supplier-invoices', [SupplierInvoiceController::class, 'store']);
    Route::post('/supplier-invoices/{supplierInvoice}/payments', [SupplierInvoiceController::class, 'pay']);

    Route::get('/procurement/payables', [ProcurementReportController::class, 'payables']);
    Route::get('/procurement/stock-valuation', [ProcurementReportController::class, 'stockValuation']);
});
