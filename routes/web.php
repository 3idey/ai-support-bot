<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DocumentController;

Route::post('/documents/upload', [DocumentController::class, 'store']);

