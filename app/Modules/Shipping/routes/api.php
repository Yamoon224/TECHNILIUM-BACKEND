<?php

declare(strict_types=1);

use App\Modules\Shipping\Http\Controllers\ShipmentController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/shipments')->middleware(['auth:sanctum', 'can:manage-shipments'])->group(function () {
    Route::get('/', [ShipmentController::class, 'index']);
    Route::post('/', [ShipmentController::class, 'store']);
    Route::get('/{shipment}/delivery-note', [ShipmentController::class, 'deliveryNote']);
    Route::post('/{shipment}/assign', [ShipmentController::class, 'assign']);
    Route::post('/{shipment}/start', [ShipmentController::class, 'start']);
    Route::post('/{shipment}/deliver', [ShipmentController::class, 'deliver']);
    Route::post('/{shipment}/fail', [ShipmentController::class, 'fail']);
    Route::post('/{shipment}/retry', [ShipmentController::class, 'retry']);
    Route::post('/{shipment}/return', [ShipmentController::class, 'returnToSender']);
});
