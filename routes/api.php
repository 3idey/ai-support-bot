<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\QuotaController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

Route::post('/ask', [ChatController::class, 'ask'])->middleware(['auth:sanctum', 'throttle:api']);

// Quota endpoints
Route::middleware(['auth:sanctum'])->prefix('quota')->group(function () {
    Route::get('/', [QuotaController::class, 'index']);
    Route::get('/remaining', [QuotaController::class, 'remaining']);
    Route::get('/tokens', [QuotaController::class, 'tokens']);
});

// Health check with Redis verification
Route::get('/health', function () {
    Cache::put('health', 'ok', 5);

    return response()->json([
        'status' => 'ok',
        'redis' => Cache::get('health') ? 'ok' : 'fail',
    ]);
});
