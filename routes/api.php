<?php

use App\Http\Controllers\LinkController;
use Illuminate\Support\Facades\Route;

Route::post('/shorten', [LinkController::class, 'store']);
Route::get('/stats/{code}', [LinkController::class, 'stats']);
