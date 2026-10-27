<?php

declare(strict_types=1);

use App\Modules\Accounting\Http\Controllers\AccountingController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/accounting')->middleware(['auth:sanctum', 'can:manage-accounting'])->group(function () {
    Route::get('/entries', [AccountingController::class, 'entries']);
    Route::get('/trial-balance', [AccountingController::class, 'trialBalance']);
});
