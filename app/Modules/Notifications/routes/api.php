<?php

declare(strict_types=1);

use App\Modules\Notifications\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/notifications')->middleware(['auth:sanctum', 'can:manage-notifications'])->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::post('/{notification}/retry', [NotificationController::class, 'retry']);
});
