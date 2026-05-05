<?php

use App\Http\Controllers\LinkController;
use Illuminate\Support\Facades\Route;

Route::post('/shorten', [LinkController::class, 'store']);
Route::patch('/shorten/{code}', [LinkController::class, 'update']);
Route::delete('/shorten/{code}', [LinkController::class, 'destroy']);
Route::get('/stats/{code}', [LinkController::class, 'stats']);
