<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

Route::post('/ask', [ChatController::class, 'ask'])->middleware(['auth:sanctum', 'throttle:10']);

// Health check with Redis verification
Route::get('/health', function () {
    Cache::put('health', 'ok', 5);

    return response()->json([
        'status' => 'ok',
        'redis' => Cache::get('health') ? 'ok' : 'fail',
    ]);
});
