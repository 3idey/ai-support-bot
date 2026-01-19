<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\QuotaController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;


Route::post('/ask', [ChatController::class, 'ask'])->middleware(['auth:sanctum', 'throttle:api']);

// Document endpoints
Route::middleware(['auth:sanctum'])->prefix('documents')->group(function () {
    Route::post('/', [DocumentController::class, 'store']);
    Route::get('/', [DocumentController::class, 'index']);
    Route::get('/{id}', [DocumentController::class, 'show']);
    Route::delete('/{id}', [DocumentController::class, 'destroy']);
});

// Workspace endpoints
Route::middleware(['auth:sanctum'])->prefix('workspaces')->group(function () {
    Route::get('/', [WorkspaceController::class, 'index']);
    Route::post('/', [WorkspaceController::class, 'store']);
    Route::get('/{id}', [WorkspaceController::class, 'show']);
    Route::put('/{id}', [WorkspaceController::class, 'update']);
    Route::delete('/{id}', [WorkspaceController::class, 'destroy']);
    Route::get('/{id}/documents', [WorkspaceController::class, 'documents']);
    Route::get('/{id}/conversations', [WorkspaceController::class, 'conversations']);
});

// Conversation endpoints
Route::middleware(['auth:sanctum'])->prefix('conversations')->group(function () {
    Route::get('/', [ConversationController::class, 'index']);
    Route::get('/{id}', [ConversationController::class, 'show']);
    Route::delete('/{id}', [ConversationController::class, 'destroy']);
    Route::get('/{id}/messages', [ConversationController::class, 'messages']);
});

// Quota endpoints
Route::middleware(['auth:sanctum'])->prefix('quota')->group(function () {
    Route::get('/', [QuotaController::class, 'index']);
    Route::get('/check', [QuotaController::class, 'check']);
    Route::get('/remaining', [QuotaController::class, 'remaining']);
    Route::get('/tokens', [QuotaController::class, 'tokens']);
    Route::get('/usage', [QuotaController::class, 'usage']);
    Route::post('/track', [QuotaController::class, 'track']);
    Route::get('/documents/{workspaceId}', [QuotaController::class, 'documentsQuota']);
    Route::post('/documents/validate-size', [QuotaController::class, 'validateDocumentSize']);
});

// Health check with Redis verification
Route::get('/health', function () {
    Cache::put('health', 'ok', 5);

    return response()->json([
        'status' => 'ok',
        'redis' => Cache::get('health') ? 'ok' : 'fail',
    ]);
});
