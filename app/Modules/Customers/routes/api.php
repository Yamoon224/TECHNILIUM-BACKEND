<?php

declare(strict_types=1);

use App\Modules\Customers\Http\Controllers\CustomerTicketController;
use App\Modules\Customers\Http\Controllers\HelpController;
use App\Modules\Customers\Http\Controllers\ReviewController;
use App\Modules\Customers\Http\Controllers\ReviewModerationController;
use App\Modules\Customers\Http\Controllers\StaffTicketController;
use Illuminate\Support\Facades\Route;

// Public: the help page.
Route::get('/help', [HelpController::class, 'show'])->middleware('throttle:60,1');

Route::prefix('reviews')->group(function () {
    // Public reading, throttled: reviews are shown on every product page.
    Route::get('/products/{productId}', [ReviewController::class, 'forProduct'])->middleware('throttle:120,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/mine', [ReviewController::class, 'mine']);
        Route::get('/products/{productId}/eligibility', [ReviewController::class, 'eligibility']);
        Route::post('/', [ReviewController::class, 'store'])->middleware('throttle:10,1');
    });
});

Route::prefix('admin/reviews')->middleware(['auth:sanctum', 'can:manage-reviews'])->group(function () {
    Route::get('/', [ReviewModerationController::class, 'index']);
    Route::post('/{review}/publish', [ReviewModerationController::class, 'publish']);
    Route::post('/{review}/reject', [ReviewModerationController::class, 'reject']);
});

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
