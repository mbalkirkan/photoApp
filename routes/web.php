<?php

use Illuminate\Support\Facades\Route;

Route::get('/', [\App\Http\Controllers\PhotoController::class, 'get']);
Route::post('/', [\App\Http\Controllers\PhotoController::class, 'upload']);

