<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Identity\Http\Controllers\AccessController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/preferences', [AuthController::class, 'updatePreferences']);
    });
});

// Access management: features, custom groups, who belongs where - super admin only.
Route::prefix('admin/access')->middleware(['auth:sanctum', 'can:manage-access'])->group(function () {
    Route::get('/', [AccessController::class, 'index']);
    Route::get('/users', [AccessController::class, 'users']);
    Route::put('/users/{user}/roles', [AccessController::class, 'assignRoles']);
    Route::post('/groups', [AccessController::class, 'storeGroup']);
    Route::put('/groups/{name}', [AccessController::class, 'updateGroup']);
    Route::delete('/groups/{name}', [AccessController::class, 'destroyGroup']);
    Route::put('/switches', [AccessController::class, 'setSwitch']);
});
