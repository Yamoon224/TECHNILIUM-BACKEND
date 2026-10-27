<?php

declare(strict_types=1);

use App\Modules\Audit\Http\Controllers\AuditController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/audit')->middleware(['auth:sanctum', 'can:view-audit'])->group(function () {
    Route::get('/', [AuditController::class, 'index']);
    Route::get('/verify', [AuditController::class, 'verify']);
});
