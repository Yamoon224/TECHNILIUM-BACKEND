<?php

declare(strict_types=1);

use App\Modules\Reporting\Http\Controllers\DashboardController;
use App\Modules\Reporting\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// Any signed-in staff account; the blocks are filtered by the features it holds.
Route::get('admin/dashboard', [DashboardController::class, 'show'])->middleware('auth:sanctum');

Route::prefix('admin/reports')->middleware(['auth:sanctum', 'can:view-reports'])->group(function () {
    Route::get('/dashboard', [ReportController::class, 'dashboard']);
    Route::get('/sales-by-seller', [ReportController::class, 'salesBySeller']);
    Route::get('/sales.csv', [ReportController::class, 'salesCsv']);
    Route::get('/margin', [ReportController::class, 'margin']);
    Route::get('/margin.csv', [ReportController::class, 'marginCsv']);
});
