<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;

Route::post('/ask', [ChatController::class, 'ask'])->middleware(['auth:sanctum', 'throttle:10']);