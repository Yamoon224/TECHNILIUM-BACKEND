<?php

declare(strict_types=1);

use App\Modules\Qr\Http\Controllers\QrCodeController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/qrcodes')->middleware(['auth:sanctum', 'can:generate-qrcodes', 'throttle:120,1'])->group(function () {
    Route::get('/payload', [QrCodeController::class, 'payload']);
});
