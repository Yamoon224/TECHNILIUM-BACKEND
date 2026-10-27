<?php

declare(strict_types=1);

use App\Modules\Customers\Http\Controllers\CustomerTicketController;
use App\Modules\Customers\Http\Controllers\StaffTicketController;
use Illuminate\Support\Facades\Route;

Route::prefix('support/tickets')->middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
    Route::get('/', [CustomerTicketController::class, 'index']);
    Route::post('/', [CustomerTicketController::class, 'store']);
    Route::get('/{ticket}', [CustomerTicketController::class, 'show']);
    Route::post('/{ticket}/messages', [CustomerTicketController::class, 'reply']);
});

Route::prefix('admin/tickets')->middleware(['auth:sanctum', 'can:manage-support'])->group(function () {
    Route::get('/', [StaffTicketController::class, 'index']);
    Route::get('/{ticket}', [StaffTicketController::class, 'show']);
    Route::post('/{ticket}/messages', [StaffTicketController::class, 'reply']);
    Route::patch('/{ticket}', [StaffTicketController::class, 'update']);
});
