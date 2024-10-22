<?php

use App\Http\Controllers\PhotoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [\App\Http\Controllers\PhotoController::class, 'get']);
Route::post('/', [\App\Http\Controllers\PhotoController::class, 'upload']);

Route::get('/photos/last-merged', [PhotoController::class, 'getMergedPhoto']);
