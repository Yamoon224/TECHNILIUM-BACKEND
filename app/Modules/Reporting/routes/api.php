<?php

declare(strict_types=1);

use App\Modules\Reporting\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/reports')->middleware(['auth:sanctum', 'can:view-reports'])->group(function () {
    Route::get('/dashboard', [ReportController::class, 'dashboard']);
    Route::get('/sales-by-seller', [ReportController::class, 'salesBySeller']);
    Route::get('/sales.csv', [ReportController::class, 'salesCsv']);
});
